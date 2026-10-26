<?php

namespace Tests\Unit;

use App\Services\Ai\CvDraftValidator;
use PHPUnit\Framework\TestCase;

class CvDraftValidatorTest extends TestCase
{
    public function test_valid_payload_is_normalised_into_sections(): void
    {
        $raw = json_encode([
            'contact' => ['full_name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => null, 'location' => 'Bandung', 'linkedin_url' => 'https://linkedin.com/in/jane', 'portfolio_url' => null, 'github_url' => null],
            'summary' => ['text' => 'Frontend developer.'],
            'experiences' => [['organization' => 'PT Foo', 'job_title' => 'Dev', 'start_date' => '2022', 'end_date' => null, 'is_current' => true]],
            'educations' => [['institution' => 'ITB', 'degree' => 'S1']],
            'skills' => [['name' => 'JavaScript'], ['name' => ''], ['category' => 'ignored']],
            'projects' => [['name' => 'Web App', 'technologies' => ['Laravel', 'React']]],
            'certifications' => [['name' => 'AWS SAA', 'issue_date' => 'Jan 2024']],
            'confidence' => ['contact' => 90, 'summary' => 80, 'skills' => 500],
        ]);

        $draft = CvDraftValidator::validate($raw);
        $sections = $draft['sections'];

        $this->assertSame('Jane Doe', $sections['contact']['data']['full_name']);
        $this->assertSame(90, $sections['contact']['confidence']);
        $this->assertSame('Frontend developer.', $sections['summary']['data']['text']);

        // Year-only date becomes January 1st of that year.
        $this->assertSame('2022-01-01', $sections['experiences']['items'][0]['start_date']);
        $this->assertTrue($sections['experiences']['items'][0]['is_current']);

        // Rows without a required name are dropped.
        $this->assertCount(1, $sections['skills']['items']);
        $this->assertSame('JavaScript', $sections['skills']['items'][0]['name']);

        $this->assertSame(['Laravel', 'React'], $sections['projects']['items'][0]['technologies']);

        // "Jan 2024" parses to a real date.
        $this->assertSame('2024-01-01', $sections['certifications']['items'][0]['issue_date']);

        // Confidence is clamped to 0-100.
        $this->assertSame(100, $sections['skills']['confidence']);
    }

    public function test_invalid_json_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid_output');

        CvDraftValidator::validate('this is not json at all');
    }

    public function test_payload_with_no_usable_data_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid_output');

        CvDraftValidator::validate(json_encode([
            'contact' => ['full_name' => null, 'email' => null],
            'experiences' => [],
            'skills' => [['category' => 'no name here']],
        ]));
    }

    public function test_json_wrapped_in_prose_is_salvaged(): void
    {
        $raw = 'Here is the result: '.json_encode([
            'summary' => ['text' => 'Salvaged summary'],
        ]).' — done.';

        $draft = CvDraftValidator::validate($raw);

        $this->assertSame('Salvaged summary', $draft['sections']['summary']['data']['text']);
    }

    public function test_invalid_urls_are_nulled_and_long_text_is_capped(): void
    {
        $raw = json_encode([
            'contact' => ['full_name' => 'A', 'linkedin_url' => 'not a url'],
            'summary' => ['text' => str_repeat('x', 10000)],
        ]);

        $draft = CvDraftValidator::validate($raw);

        $this->assertNull($draft['sections']['contact']['data']['linkedin_url']);
        $this->assertSame(4000, mb_strlen($draft['sections']['summary']['data']['text']));
    }

    public function test_unparseable_dates_are_nulled(): void
    {
        $raw = json_encode([
            'experiences' => [['organization' => 'X', 'job_title' => 'Y', 'start_date' => 'sometime in the past', 'end_date' => 'not a date']],
        ]);

        $draft = CvDraftValidator::validate($raw);
        $item = $draft['sections']['experiences']['items'][0];

        $this->assertNull($item['start_date']);
        $this->assertNull($item['end_date']);
    }
}
