<?php

namespace Tests\Unit;

use App\Services\Ai\DocumentFactValidator;
use PHPUnit\Framework\TestCase;

class DocumentFactValidatorTest extends TestCase
{
    private function snapshot(): array
    {
        return [
            'contact' => ['full_name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => null,
                'location' => 'Jakarta', 'linkedin_url' => null, 'portfolio_url' => null, 'github_url' => null],
            'summary' => 'Backend engineer with 5 years experience.',
            'experiences' => [[
                'id' => 3, 'organization' => 'Acme Corp', 'job_title' => 'Senior Engineer',
                'start_date' => '2022-01-01', 'end_date' => null, 'is_current' => true,
                'description' => 'Led a team of 4 engineers.', 'achievements' => ['Reduced latency by 40%'],
            ]],
            'educations' => [['id' => 7, 'institution' => 'ITB', 'degree' => 'S1', 'field_of_study' => 'Informatika',
                'start_date' => '2015-01-01', 'end_date' => '2019-01-01', 'description' => null]],
            'skills' => [['id' => 5, 'name' => 'PHP', 'category' => 'Backend', 'proficiency' => null]],
            'projects' => [['id' => 2, 'name' => 'Exam Platform', 'role' => 'Lead', 'description' => null,
                'technologies' => ['Laravel'], 'url' => null, 'start_date' => null, 'end_date' => null]],
            'certifications' => [['id' => 9, 'name' => 'AWS SAA', 'issuer' => 'Amazon', 'issue_date' => '2023-01-01', 'expiry_date' => null]],
        ];
    }

    public function test_valid_resume_is_normalised_and_keeps_source_facts(): void
    {
        $raw = json_encode([
            'summary' => 'Senior backend engineer focused on PHP.',
            'skills' => [['name' => 'PHP', 'category' => 'Backend', 'evidence_refs' => ['skill:5']]],
            'experiences' => [[
                'organization' => 'Acme Corp',
                'role' => 'Senior Engineer',
                'start_date' => '2020-01-01', // model tries to change the date
                'end_date' => '2024-01-01',
                'is_current' => false,
                'bullets' => ['Led a team of 4 engineers.', 'Reduced latency by 40%'],
                'evidence_refs' => ['experience:3'],
            ]],
            'education' => [['institution' => 'ITB', 'degree' => 'S1', 'evidence_refs' => ['education:7']]],
            'certifications' => [['name' => 'AWS SAA', 'issuer' => 'Amazon']],
            'projects' => [['name' => 'Exam Platform', 'bullets' => ['Built an exam platform.'], 'technologies' => ['Laravel']]],
            'languages' => ['Indonesian', 'English'],
            'unverified_claims' => [],
        ]);

        $result = DocumentFactValidator::validateResume($raw, $this->snapshot());
        $experience = $result['experiences'][0];

        // Dates come from the snapshot, never from the model.
        $this->assertSame('2022-01-01', $experience['start_date']);
        $this->assertNull($experience['end_date']);
        $this->assertTrue($experience['is_current']);
        $this->assertSame(['experience:3'], $experience['evidence_refs']);

        // Bullets are kept; both metrics exist in the profile so nothing is flagged.
        $this->assertCount(2, $experience['bullets']);
        $this->assertSame([], $result['unverified_claims']);
        $this->assertSame('v2', $result['prompt_version']);
        $this->assertSame(0, $result['dropped']);
    }

    public function test_invented_employer_is_dropped(): void
    {
        $raw = json_encode([
            'summary' => 'x',
            'experiences' => [
                ['organization' => 'Acme Corp', 'role' => 'Senior Engineer', 'bullets' => ['Did work.'], 'evidence_refs' => ['experience:3']],
                ['organization' => 'Google', 'role' => 'Staff Engineer', 'bullets' => ['Led 20 engineers.'], 'evidence_refs' => ['experience:999']],
            ],
        ]);

        $result = DocumentFactValidator::validateResume($raw, $this->snapshot());

        $this->assertCount(1, $result['experiences']);
        $this->assertSame('Acme Corp', $result['experiences'][0]['organization']);
        $this->assertSame(1, $result['dropped']);
    }

    public function test_invented_skill_is_dropped(): void
    {
        $raw = json_encode([
            'summary' => 'x',
            'skills' => [
                ['name' => 'PHP', 'evidence_refs' => []],
                ['name' => 'Kubernetes', 'evidence_refs' => ['skill:999']],
            ],
        ]);

        $result = DocumentFactValidator::validateResume($raw, $this->snapshot());

        $this->assertCount(1, $result['skills']);
        $this->assertSame('PHP', $result['skills'][0]['name']);
        // Evidence is backfilled from the matching snapshot row.
        $this->assertSame(['skill:5'], $result['skills'][0]['evidence_refs']);
    }

    public function test_unsupported_metric_is_flagged_but_kept(): void
    {
        $raw = json_encode([
            'summary' => 'x',
            'experiences' => [[
                'organization' => 'Acme Corp',
                'role' => 'Senior Engineer',
                'bullets' => ['Increased revenue by 250% in one quarter.'],
                'evidence_refs' => ['experience:3'],
            ]],
        ]);

        $result = DocumentFactValidator::validateResume($raw, $this->snapshot());

        $this->assertCount(1, $result['experiences'][0]['bullets']);
        $this->assertNotEmpty($result['unverified_claims']);
        $this->assertStringContainsString('250', $result['unverified_claims'][0]);
    }

    public function test_empty_payload_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        DocumentFactValidator::validateResume('not json', $this->snapshot());
    }

    public function test_cover_letter_uses_neutral_greeting_when_no_recipient(): void
    {
        $raw = json_encode([
            'recipient_line' => 'Dear Hiring Manager,',
            'opening' => 'I am applying for the Senior Engineer role.',
            'body_paragraphs' => [['text' => 'At Acme Corp I led a team of 4 engineers.', 'evidence_refs' => ['experience:3']]],
            'closing' => 'Thank you.',
            'signature' => 'Jane Doe',
        ]);

        $result = DocumentFactValidator::validateCoverLetter($raw, $this->snapshot(), ['language' => 'en']);

        $this->assertSame('Dear Hiring Manager,', $result['recipient_line']);
        $this->assertCount(1, $result['body_paragraphs']);
        $this->assertSame(['experience:3'], $result['body_paragraphs'][0]['evidence_refs']);
        $this->assertSame('v2', $result['prompt_version']);
    }

    public function test_cover_letter_rejects_invented_recruiter_name(): void
    {
        // The model invents "Mr. John Smith" — no name was supplied.
        $raw = json_encode([
            'recipient_line' => 'Dear Mr. John Smith,',
            'opening' => 'Hello.',
            'body_paragraphs' => [],
        ]);

        $result = DocumentFactValidator::validateCoverLetter($raw, $this->snapshot(), ['language' => 'id']);

        $this->assertSame('Kepada Tim Rekrutmen,', $result['recipient_line']);
    }

    public function test_cover_letter_flags_unsupported_metric(): void
    {
        $raw = json_encode([
            'recipient_line' => 'Dear Hiring Manager,',
            'opening' => 'Hello.',
            'body_paragraphs' => [['text' => 'I increased sales by 87%.', 'evidence_refs' => []]],
        ]);

        $result = DocumentFactValidator::validateCoverLetter($raw, $this->snapshot(), ['language' => 'en']);

        $this->assertNotEmpty($result['unverified_claims']);
    }
}
