<?php

namespace Tests\Unit;

use App\Services\Ai\JobFitAnalysisValidator;
use PHPUnit\Framework\TestCase;

class JobFitAnalysisValidatorTest extends TestCase
{
    private function snapshot(): array
    {
        return [
            'experiences' => [['id' => 3, 'organization' => 'Acme', 'job_title' => 'Dev']],
            'educations' => [['id' => 7, 'institution' => 'ITB']],
            'skills' => [['id' => 5, 'name' => 'PHP'], ['id' => 6, 'name' => 'Laravel']],
            'projects' => [['id' => 2, 'name' => 'Exam platform']],
            'certifications' => [['id' => 9, 'name' => 'AWS SAA']],
        ];
    }

    public function test_valid_payload_passes_with_refs_kept(): void
    {
        $raw = json_encode([
            'requirements' => [[
                'text' => 'Strong PHP',
                'category' => 'skill',
                'priority' => 'must_have',
                'match_status' => 'matched',
                'evidence_refs' => ['skill:5', '[skill:6]'],
                'recommendation' => null,
            ]],
            'keyword_suggestions' => [
                ['keyword' => 'PHP', 'supported_by_profile' => true, 'evidence_refs' => ['skill:5']],
            ],
            'seniority' => 'Mid',
            'summary' => 'Coverage is good.',
        ]);

        $result = JobFitAnalysisValidator::validate($raw, $this->snapshot());

        $this->assertSame(['skill:5', 'skill:6'], $result['requirements'][0]['evidence_refs']);
        $this->assertSame(0, $result['demoted']);
        $this->assertTrue($result['keyword_suggestions'][0]['supported_by_profile']);
    }

    public function test_unknown_refs_are_dropped_and_claims_demoted(): void
    {
        $raw = json_encode([
            'requirements' => [[
                'text' => 'Kubernetes',
                'category' => 'tool',
                'priority' => 'must_have',
                'match_status' => 'matched',
                'evidence_refs' => ['skill:999', 'experience:404'],
                'recommendation' => 'Add real experience.',
            ]],
            'summary' => 'x',
        ]);

        $result = JobFitAnalysisValidator::validate($raw, $this->snapshot());

        $this->assertSame([], $result['requirements'][0]['evidence_refs']);
        $this->assertSame('not_evidenced', $result['requirements'][0]['match_status']);
        $this->assertSame(1, $result['demoted']);
    }

    public function test_partial_with_valid_evidence_is_kept(): void
    {
        $raw = json_encode([
            'requirements' => [[
                'text' => 'Mentoring experience',
                'category' => 'responsibility',
                'priority' => 'nice_to_have',
                'match_status' => 'partial',
                'evidence_refs' => ['experience:3'],
                'recommendation' => 'Describe mentoring in your Acme role.',
            ]],
            'summary' => 'x',
        ]);

        $result = JobFitAnalysisValidator::validate($raw, $this->snapshot());

        $this->assertSame('partial', $result['requirements'][0]['match_status']);
        $this->assertSame(['experience:3'], $result['requirements'][0]['evidence_refs']);
    }

    public function test_invalid_category_and_priority_fall_back_to_defaults(): void
    {
        $raw = json_encode([
            'requirements' => [[
                'text' => 'Something',
                'category' => 'nonsense',
                'priority' => 'urgent',
                'match_status' => 'matched',
                'evidence_refs' => ['skill:5'],
            ]],
            'summary' => 'x',
        ]);

        $result = JobFitAnalysisValidator::validate($raw, $this->snapshot());

        $this->assertSame('required_qualification', $result['requirements'][0]['category']);
        $this->assertSame('must_have', $result['requirements'][0]['priority']);
    }

    public function test_must_have_requirements_sort_first(): void
    {
        $raw = json_encode([
            'requirements' => [
                ['text' => 'Nice one', 'category' => 'skill', 'priority' => 'nice_to_have', 'match_status' => 'not_evidenced', 'evidence_refs' => []],
                ['text' => 'Must one', 'category' => 'skill', 'priority' => 'must_have', 'match_status' => 'not_evidenced', 'evidence_refs' => []],
            ],
            'summary' => 'x',
        ]);

        $result = JobFitAnalysisValidator::validate($raw, $this->snapshot());

        $this->assertSame('Must one', $result['requirements'][0]['text']);
        $this->assertSame('Nice one', $result['requirements'][1]['text']);
    }

    public function test_empty_or_unparseable_output_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        JobFitAnalysisValidator::validate('not json', $this->snapshot());
    }

    public function test_requirement_count_is_capped(): void
    {
        $requirements = [];
        for ($i = 0; $i < 40; $i++) {
            $requirements[] = ['text' => "Requirement {$i}", 'category' => 'skill', 'priority' => 'must_have', 'match_status' => 'not_evidenced', 'evidence_refs' => []];
        }

        $result = JobFitAnalysisValidator::validate(json_encode([
            'requirements' => $requirements,
            'summary' => 'x',
        ]), $this->snapshot());

        $this->assertCount(20, $result['requirements']);
    }
}
