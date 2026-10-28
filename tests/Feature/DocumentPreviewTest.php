<?php

namespace Tests\Feature;

use App\Models\GeneratedDocument;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\DocumentTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function document(User $user, array $overrides = []): GeneratedDocument
    {
        $profile = $user->careerProfile()->create(['summary' => 'Backend engineer.']);
        $profile->contact()->create([
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+62 812 0000',
            'location' => 'Jakarta',
        ]);

        $application = JobApplication::create([
            'user_id' => $user->id,
            'career_profile_id' => $profile->id,
            'company_name' => 'Global Fintech',
            'job_title' => 'Senior Backend Engineer',
            'job_description' => str_repeat('Job description text. ', 10),
            'language' => 'id',
        ]);

        return $application->generatedDocuments()->create([
            'type' => GeneratedDocument::TYPE_RESUME,
            'title' => 'CV — Senior Backend Engineer',
            'version' => 1,
            'language' => 'id',
            'generation_status' => GeneratedDocument::STATUS_COMPLETED,
            'content_json' => [
                'summary' => 'Backend engineer with Laravel focus.',
                'skills' => [['name' => 'PHP'], ['name' => 'Laravel']],
                'experiences' => [[
                    'organization' => 'Acme Corp', 'role' => 'Senior Engineer',
                    'start_date' => '2022-01-01', 'end_date' => null, 'is_current' => true,
                    'bullets' => ['Led a team of 4 engineers.', 'Shipped the exam platform.'],
                ]],
                'education' => [[
                    'institution' => 'ITB', 'degree' => 'S1', 'field_of_study' => 'Informatika',
                    'start_date' => '2015-01-01', 'end_date' => '2019-01-01',
                ]],
                'certifications' => [['name' => 'AWS SAA', 'issuer' => 'Amazon', 'issue_date' => '2023-01-01']],
                'languages' => ['Indonesia', 'English'],
            ],
            ...$overrides,
        ]);
    }

    public function test_preview_returns_single_column_ats_html(): void
    {
        $user = User::factory()->create();
        $document = $this->document($user);

        $response = $this->actingAs($user)->get(route('documents.preview', $document));

        $response->assertOk();
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));

        $html = $response->getContent();

        // Standard headings in the document language.
        $this->assertStringContainsString('RINGKASAN', $html);
        $this->assertStringContainsString('PENGALAMAN KERJA', $html);
        $this->assertStringContainsString('PENDIDIKAN', $html);

        // ATS constraints: no tables, no images, no icons.
        $this->assertStringNotContainsString('<table', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<svg', $html);

        // Content is present and escaped.
        $this->assertStringContainsString('Jane Doe', $html);
        $this->assertStringContainsString('Acme Corp', $html);
        $this->assertStringContainsString('Led a team of 4 engineers.', $html);
    }

    public function test_preview_uses_english_headings_for_english_documents(): void
    {
        $user = User::factory()->create();
        $document = $this->document($user, ['language' => 'en']);

        $html = $this->actingAs($user)->get(route('documents.preview', $document))->getContent();

        $this->assertStringContainsString('SUMMARY', $html);
        $this->assertStringContainsString('EXPERIENCE', $html);
        $this->assertStringNotContainsString('RINGKASAN', $html);
    }

    public function test_preview_follows_stored_section_order(): void
    {
        $user = User::factory()->create();
        $document = $this->document($user, [
            'section_order' => ['experiences', 'summary', 'skills'],
        ]);

        $html = $this->actingAs($user)->get(route('documents.preview', $document))->getContent();

        $experiencePos = strpos($html, 'PENGALAMAN KERJA');
        $summaryPos = strpos($html, 'RINGKASAN');

        $this->assertNotFalse($experiencePos);
        $this->assertNotFalse($summaryPos);
        $this->assertLessThan($summaryPos, $experiencePos, 'Experience section must render before summary.');
    }

    public function test_compact_template_changes_typography_not_structure(): void
    {
        $user = User::factory()->create();

        // One profile per user; both documents hang off the same application.
        $plain = $this->document($user, ['template_key' => 'ats_plain']);
        $compact = $plain->jobApplication->generatedDocuments()->create([
            'type' => GeneratedDocument::TYPE_RESUME,
            'title' => 'compact',
            'template_key' => 'ats_compact',
            'version' => 2,
            'language' => 'id',
            'generation_status' => GeneratedDocument::STATUS_COMPLETED,
            'content_json' => $plain->content_json,
        ]);

        $renderer = app(DocumentTemplateRenderer::class);

        $plainHtml = $renderer->renderPage($plain);
        $compactHtml = $renderer->renderPage($compact);

        $this->assertStringContainsString('font-size:11pt', $plainHtml);
        $this->assertStringContainsString('font-size:10.5pt', $compactHtml);

        // Both keep the same headings and still avoid tables.
        foreach ([$plainHtml, $compactHtml] as $html) {
            $this->assertStringContainsString('PENGALAMAN KERJA', $html);
            $this->assertStringNotContainsString('<table', $html);
        }
    }

    public function test_cover_letter_preview_orders_greeting_body_closing(): void
    {
        $user = User::factory()->create();

        $document = $this->document($user, [
            'type' => GeneratedDocument::TYPE_COVER_LETTER,
            'content_json' => [
                'recipient_line' => 'Kepada Tim Rekrutmen,',
                'opening' => 'Saya melamar posisi Senior Backend Engineer.',
                'body_paragraphs' => [
                    ['text' => 'Paragraf pertama tentang pengalaman.'],
                    ['text' => 'Paragraf kedua tentang kecocokan.'],
                ],
                'closing' => 'Terima kasih atas perhatiannya.',
                'signature' => 'Jane Doe',
            ],
        ]);

        $html = $this->actingAs($user)->get(route('documents.preview', $document))->getContent();

        $recipientPos = strpos($html, 'Kepada Tim Rekrutmen');
        $openingPos = strpos($html, 'Saya melamar posisi');
        $bodyPos = strpos($html, 'Paragraf pertama');
        $closingPos = strpos($html, 'Terima kasih');

        $this->assertNotFalse($recipientPos);
        $this->assertLessThan($openingPos, $recipientPos);
        $this->assertLessThan($bodyPos, $openingPos);
        $this->assertLessThan($closingPos, $bodyPos);

        // Cover letters never get resume section headings.
        $this->assertStringNotContainsString('PENGALAMAN KERJA', $html);
    }

    public function test_preview_escapes_html_from_model_output(): void
    {
        $user = User::factory()->create();

        $document = $this->document($user, [
            'content_json' => [
                'summary' => '<script>alert("xss")</script> normal text',
                'skills' => [],
                'experiences' => [],
            ],
        ]);

        $html = $this->actingAs($user)->get(route('documents.preview', $document))->getContent();

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_guest_cannot_view_preview(): void
    {
        $user = User::factory()->create();
        $document = $this->document($user);

        $this->get(route('documents.preview', $document))->assertRedirect('/login');
    }
}
