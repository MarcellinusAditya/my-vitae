<?php

namespace Tests\Feature;

use App\Models\GeneratedDocument;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\DocumentOutlineBuilder;
use App\Services\DocumentTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dates in a CV are read by recruiters, so they must never leak raw ISO strings
 * ("2022-03-01") or an Indonesian "sekarang" into an English document. The
 * format comes from the career profile's date_format preference.
 */
class DocumentDateFormatTest extends TestCase
{
    use RefreshDatabase;

    private function documentFor(string $language, string $dateFormat = 'month_year'): GeneratedDocument
    {
        $user = User::factory()->create();
        $profile = $user->careerProfile()->create([
            'summary' => 'Engineer.',
            'date_format' => $dateFormat,
        ]);

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
            'template_key' => 'ats_plain',
            'generation_status' => GeneratedDocument::STATUS_COMPLETED,
            'content_json' => [
                'summary' => 'Engineer.',
                'experiences' => [
                    [
                        'organization' => 'PT Maju',
                        'title' => 'Engineer',
                        'start_date' => '2022-03-01',
                        'end_date' => null,
                        'is_current' => true,
                        'bullets' => ['Built things.'],
                    ],
                    [
                        'organization' => 'Old Co',
                        'title' => 'Junior',
                        'start_date' => '2019-08-01',
                        'end_date' => '2022-02-28',
                        'is_current' => false,
                        'bullets' => [],
                    ],
                ],
            ],
        ]);
    }

    private function dateLines(GeneratedDocument $document): array
    {
        $outline = app(DocumentOutlineBuilder::class)->build($document);
        $lines = [];

        foreach ($outline['sections'] as $section) {
            foreach ($section['blocks'] as $block) {
                if (isset($block['dates']) && $block['dates'] !== '') {
                    $lines[] = $block['dates'];
                }
            }
        }

        return $lines;
    }

    public function test_english_document_uses_english_present_label(): void
    {
        $dates = $this->dateLines($this->documentFor('en'));

        $this->assertContains('Mar 2022 – Present', $dates);
        $this->assertNotContains('Mar 2022 – sekarang', $dates);
    }

    public function test_indonesian_document_keeps_indonesian_label(): void
    {
        $this->assertContains('Mar 2022 – sekarang', $this->dateLines($this->documentFor('id')));
    }

    public function test_malay_document_uses_malay_label(): void
    {
        // Malay month abbreviation differs from English: Mac, not Mar.
        $this->assertContains('Mac 2022 – kini', $this->dateLines($this->documentFor('ms')));
    }

    public function test_profile_date_format_is_honoured(): void
    {
        $numeric = $this->dateLines($this->documentFor('en', 'numeric'));
        $this->assertContains('03/2022 – Present', $numeric);

        $yearOnly = $this->dateLines($this->documentFor('en', 'year_only'));
        $this->assertContains('2022 – Present', $yearOnly);
    }

    public function test_raw_iso_dates_never_reach_the_rendered_page(): void
    {
        $html = app(DocumentTemplateRenderer::class)->renderPage($this->documentFor('en'));

        $this->assertStringNotContainsString('2022-03-01', $html);
        $this->assertStringNotContainsString('2019-08-01', $html);
        $this->assertStringContainsString('Aug 2019 – Feb 2022', $html);
    }

    public function test_unparseable_dates_fall_back_to_the_original_text(): void
    {
        $document = $this->documentFor('en');
        $content = $document->content_json;
        $content['experiences'][0]['start_date'] = 'Sejak awal';
        $document->update(['content_json' => $content]);

        $this->assertContains('Sejak awal – Present', $this->dateLines($document->fresh()));
    }
}
