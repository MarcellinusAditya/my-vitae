<?php

namespace Tests\Feature;

use App\Models\GeneratedDocument;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\DocumentTemplateRenderer;
use App\Services\Exports\DocxExporter;
use App\Services\Exports\PdfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class DocumentTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function document(string $templateKey, string $language = 'id'): GeneratedDocument
    {
        $user = User::factory()->create();
        $profile = $user->careerProfile()->create(['summary' => 'Backend engineer.']);
        $application = JobApplication::create([
            'user_id' => $user->id,
            'career_profile_id' => $profile->id,
            'company_name' => 'Acme',
            'job_title' => 'Backend Engineer',
            'job_description' => str_repeat('text ', 30),
            'language' => $language,
        ]);

        return $application->generatedDocuments()->create([
            'type' => GeneratedDocument::TYPE_RESUME,
            'title' => 'CV',
            'version' => 1,
            'language' => $language,
            'template_key' => $templateKey,
            'generation_status' => GeneratedDocument::STATUS_COMPLETED,
            'content_json' => [
                'summary' => 'Backend engineer dengan pengalaman Laravel.',
                'skills' => ['PHP', 'Laravel'],
                'experiences' => [[
                    'organization' => 'PT Maju',
                    'title' => 'Engineer',
                    'start_date' => '2021-01',
                    'bullets' => ['Membangun API Laravel.'],
                ]],
            ],
        ]);
    }

    public function test_every_template_renders_a_distinct_single_column_page(): void
    {
        $renderer = app(DocumentTemplateRenderer::class);
        $seen = [];

        foreach (array_keys(GeneratedDocument::TEMPLATES) as $template) {
            $html = $renderer->renderPage($this->document($template));

            $this->assertStringContainsString('<h1>', $html, $template);
            // ATS-safe: no layout tables, icons or images in any template.
            $this->assertStringNotContainsString('<table', $html, $template);
            $this->assertStringNotContainsString('<img', $html, $template);
            $this->assertStringNotContainsString('flex', $html, $template);

            $seen[$template] = $html;
        }

        // Each template must actually differ, not just repeat ats_plain.
        $this->assertCount(count(GeneratedDocument::TEMPLATES), array_unique(array_values($seen)));
    }

    public function test_template_styles_are_resolved_by_key_with_a_safe_fallback(): void
    {
        $this->assertArrayHasKey('body', DocumentTemplateRenderer::styleFor('modern_minimal'));
        // Unknown/legacy keys must not break rendering.
        $this->assertSame(
            DocumentTemplateRenderer::TEMPLATE_STYLES['ats_plain'],
            DocumentTemplateRenderer::styleFor('does_not_exist'),
        );
    }

    public function test_pdf_export_of_each_template_keeps_selectable_text(): void
    {
        $exporter = app(PdfExporter::class);

        foreach (array_keys(GeneratedDocument::TEMPLATES) as $template) {
            $bytes = $exporter->render($this->document($template));

            $path = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
            file_put_contents($path, $bytes);

            try {
                $text = (new Parser)->parseFile($path)->getText();

                $this->assertStringContainsString('PENGALAMAN KERJA', $text, $template);
                $this->assertStringContainsString('PT Maju', $text, $template);
                $this->assertStringContainsString('Membangun API Laravel', $text, $template);
            } finally {
                @unlink($path);
            }
        }
    }

    public function test_docx_export_works_for_a_new_template(): void
    {
        $bytes = app(DocxExporter::class)->render($this->document('modern_minimal'));

        $this->assertSame('PK', substr($bytes, 0, 2), 'DOCX must be a zip container');
        $this->assertGreaterThan(1000, strlen($bytes));
    }

    public function test_malay_language_uses_malay_headings(): void
    {
        $html = app(DocumentTemplateRenderer::class)->renderPage($this->document('ats_plain', 'ms'));

        $this->assertStringContainsString('PENGALAMAN KERJA', $html);
        $this->assertStringContainsString('KEMAHIRAN', $html);
        $this->assertStringContainsString('<html lang="ms">', $html);
    }
}
