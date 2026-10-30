<?php

namespace Tests\Unit;

use App\Models\GeneratedDocument;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\DocumentOutlineBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentOutlineTest extends TestCase
{
    use RefreshDatabase;

    private function document(array $overrides = []): GeneratedDocument
    {
        $user = User::factory()->create();
        $profile = $user->careerProfile()->create(['summary' => 'Engineer.']);
        $profile->contact()->create(['full_name' => 'Jane Doe', 'email' => 'jane@example.com']);

        $application = JobApplication::create([
            'user_id' => $user->id,
            'career_profile_id' => $profile->id,
            'company_name' => 'Acme',
            'job_title' => 'Dev',
            'job_description' => str_repeat('text ', 20),
            'language' => 'id',
        ]);

        return $application->generatedDocuments()->create([
            'type' => GeneratedDocument::TYPE_RESUME,
            'title' => 'CV',
            'version' => 1,
            'language' => 'id',
            'generation_status' => GeneratedDocument::STATUS_COMPLETED,
            'content_json' => [
                'summary' => 'Summary text.',
                'skills' => [['name' => 'PHP'], ['name' => 'Laravel']],
                'experiences' => [[
                    'organization' => 'Acme', 'role' => 'Dev', 'start_date' => '2022-01-01',
                    'is_current' => true, 'bullets' => ['Did things.'],
                ]],
            ],
            ...$overrides,
        ]);
    }

    public function test_outline_follows_section_order(): void
    {
        $document = $this->document(['section_order' => ['experiences', 'skills', 'summary']]);

        $outline = app(DocumentOutlineBuilder::class)->build($document);

        $this->assertSame(
            ['experiences', 'skills', 'summary'],
            array_column($outline['sections'], 'key'),
        );
    }

    public function test_empty_sections_are_omitted(): void
    {
        $document = $this->document();

        $outline = app(DocumentOutlineBuilder::class)->build($document);
        $keys = array_column($outline['sections'], 'key');

        $this->assertContains('summary', $keys);
        $this->assertContains('experiences', $keys);
        // No education/projects/certifications content -> not rendered.
        $this->assertNotContains('education', $keys);
        $this->assertNotContains('projects', $keys);
        $this->assertNotContains('certifications', $keys);
    }

    public function test_headings_follow_document_language(): void
    {
        $id = app(DocumentOutlineBuilder::class)->build($this->document());
        $en = app(DocumentOutlineBuilder::class)->build($this->document(['language' => 'en']));

        $this->assertSame('RINGKASAN', $id['sections'][0]['heading']);
        $this->assertSame('SUMMARY', $en['sections'][0]['heading']);
    }

    public function test_experience_entry_carries_structured_fields(): void
    {
        $outline = app(DocumentOutlineBuilder::class)->build($this->document());
        $experiences = collect($outline['sections'])->firstWhere('key', 'experiences');

        $entry = $experiences['blocks'][0];

        $this->assertSame('entry', $entry['type']);
        $this->assertSame('Dev', $entry['title']);
        $this->assertSame('Acme', $entry['subtitle']);
        $this->assertSame('2022-01-01 – sekarang', $entry['dates']);
        $this->assertSame(['Did things.'], $entry['items']);
    }

    public function test_cover_letter_outline_is_a_single_block_sequence(): void
    {
        $document = $this->document([
            'type' => GeneratedDocument::TYPE_COVER_LETTER,
            'content_json' => [
                'recipient_line' => 'Kepada Tim Rekrutmen,',
                'opening' => 'Saya melamar.',
                'body_paragraphs' => [['text' => 'Paragraf satu.'], ['text' => 'Paragraf dua.']],
                'closing' => 'Terima kasih.',
                'signature' => 'Jane Doe',
            ],
        ]);

        $outline = app(DocumentOutlineBuilder::class)->build($document);

        $this->assertSame(GeneratedDocument::TYPE_COVER_LETTER, $outline['type']);
        $this->assertCount(1, $outline['sections']);
        $this->assertSame('', $outline['sections'][0]['heading']);

        $texts = array_column($outline['sections'][0]['blocks'], 'text');
        $this->assertSame([
            'Kepada Tim Rekrutmen,',
            'Saya melamar.',
            'Paragraf satu.',
            'Paragraf dua.',
            'Terima kasih.',
            'Jane Doe',
        ], $texts);
    }
}
