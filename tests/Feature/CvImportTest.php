<?php

namespace Tests\Feature;

use App\Jobs\ExtractCvDocumentJob;
use App\Models\ProfileDocument;
use App\Models\User;
use App\Services\Ai\CvExtractionProvider;
use App\Services\CvTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CvImportTest extends TestCase
{
    use RefreshDatabase;

    // -- Helpers -----------------------------------------------------------------

    /** Build a minimal, text-bearing PDF (single page, Helvetica). */
    private function pdfBytes(string $text = 'John Doe - Software Engineer. Ten years building production web applications in PHP, Laravel and React. Led teams, shipped APIs, and mentored engineers across multiple companies in Jakarta.'): string
    {
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objs[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>';
        $stream = "BT /F1 12 Tf 72 720 Td ({$text}) Tj ET";
        $objs[4] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
        $objs[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $i => $o) {
            $offsets[$i] = strlen($pdf);
            $pdf .= "{$i} 0 obj\n{$o}\nendobj\n";
        }
        $xref = strlen($pdf);
        $count = count($objs) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function createDocument(User $user, string $path = 'cv-uploads/cv.pdf'): ProfileDocument
    {
        return $user->documents()->create([
            'career_profile_id' => $user->careerProfile()->firstOrCreate([])->id,
            'original_filename' => 'cv.pdf',
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'extraction_status' => ProfileDocument::STATUS_PENDING,
        ]);
    }

    /** @param array<string, mixed> $draft */
    private function stubProvider(array $draft): CvExtractionProvider
    {
        return new class($draft) implements CvExtractionProvider
        {
            public function __construct(private array $draft) {}

            public function name(): string
            {
                return 'stub';
            }

            public function extractDraft(string $cvText): array
            {
                return $this->draft;
            }
        };
    }

    private function failingProvider(): CvExtractionProvider
    {
        return new class implements CvExtractionProvider
        {
            public function name(): string
            {
                return 'stub-failing';
            }

            public function extractDraft(string $cvText): array
            {
                throw new \RuntimeException('provider_unavailable');
            }
        };
    }

    private function sampleDraft(): array
    {
        return ['sections' => [
            'contact' => ['data' => [
                'full_name' => 'John Doe', 'email' => 'john@example.com', 'phone' => null,
                'location' => 'Jakarta', 'linkedin_url' => null, 'portfolio_url' => null, 'github_url' => null,
            ], 'confidence' => 80],
            'summary' => ['data' => ['text' => 'Backend engineer.'], 'confidence' => 70],
            'experiences' => ['items' => [[
                'organization' => 'Acme', 'job_title' => 'Engineer', 'employment_type' => null,
                'location' => null, 'start_date' => '2023-01-01', 'end_date' => null,
                'is_current' => true, 'description' => null, 'achievements' => [],
            ]], 'confidence' => 60],
            'educations' => ['items' => [], 'confidence' => 50],
            'skills' => ['items' => [['name' => 'PHP', 'category' => null, 'proficiency' => null]], 'confidence' => 90],
            'projects' => ['items' => [], 'confidence' => 50],
            'certifications' => ['items' => [], 'confidence' => 50],
        ]];
    }

    // -- Tests -------------------------------------------------------------------

    public function test_import_page_loads(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/career-profile/import')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CareerProfile/Import/Index')
                ->has('documents')
                ->where('maxFileSizeMb', 10));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/career-profile/import')->assertRedirect('/login');
    }

    public function test_upload_stores_document_and_dispatches_job(): void
    {
        Queue::fake();
        Storage::fake('local');
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from('/career-profile/import')
            ->post('/career-profile/documents', [
                'document' => UploadedFile::fake()->createWithContent('cv.pdf', $this->pdfBytes()),
            ]);

        $document = ProfileDocument::firstOrFail();

        $response->assertRedirect(route('career-profile.import.show', $document));

        $this->assertSame(ProfileDocument::STATUS_PENDING, $document->extraction_status);
        $this->assertSame('cv.pdf', $document->original_filename);
        $this->assertSame($user->id, $document->user_id);
        Storage::disk('local')->assertExists($document->storage_path);
        $this->assertStringStartsWith('cv-uploads/', $document->storage_path);

        Queue::assertPushed(ExtractCvDocumentJob::class, fn ($job) => $job->documentId === $document->id);
    }

    public function test_upload_rejects_unsupported_file_type(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/career-profile/import')
            ->post('/career-profile/documents', [
                'document' => UploadedFile::fake()->createWithContent('cv.txt', str_repeat('hello ', 100)),
            ])
            ->assertSessionHasErrors('document');

        $this->assertDatabaseCount('profile_documents', 0);
    }

    public function test_job_stores_validated_draft_on_success(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        Storage::disk('local')->put('cv-uploads/cv.pdf', $this->pdfBytes());

        $document = $this->createDocument($user);

        (new ExtractCvDocumentJob($document->id))
            ->handle(new CvTextExtractor, $this->stubProvider($this->sampleDraft()));

        $document->refresh();

        $this->assertSame(ProfileDocument::STATUS_COMPLETED, $document->extraction_status);
        $this->assertNull($document->extraction_error_code);
        $this->assertTrue($document->hasDraft());
        $this->assertSame('John Doe', $document->draft['sections']['contact']['data']['full_name']);
        $this->assertSame('stub', explode(':', $document->model_name)[0]);
    }

    public function test_job_reports_no_text_for_nearly_empty_documents(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        Storage::disk('local')->put('cv-uploads/scan.pdf', $this->pdfBytes('hi'));

        $document = $this->createDocument($user, 'cv-uploads/scan.pdf');

        (new ExtractCvDocumentJob($document->id))
            ->handle(new CvTextExtractor, $this->stubProvider($this->sampleDraft()));

        $document->refresh();

        $this->assertSame(ProfileDocument::STATUS_FAILED, $document->extraction_status);
        $this->assertSame('no_text', $document->extraction_error_code);
        $this->assertNotNull($document->errorMessage());
    }

    public function test_job_reports_provider_failure_without_crashing(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        Storage::disk('local')->put('cv-uploads/cv.pdf', $this->pdfBytes());

        $document = $this->createDocument($user);

        (new ExtractCvDocumentJob($document->id))
            ->handle(new CvTextExtractor, $this->failingProvider());

        $document->refresh();

        $this->assertSame(ProfileDocument::STATUS_FAILED, $document->extraction_status);
        $this->assertSame('provider_unavailable', $document->extraction_error_code);
    }

    public function test_other_user_cannot_access_document(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $other->careerProfile()->create();

        $document = $this->createDocument($owner);
        Storage::disk('local')->put($document->storage_path, $this->pdfBytes());

        $this->actingAs($other)
            ->get(route('career-profile.import.show', $document))
            ->assertForbidden();

        $this->actingAs($other)
            ->get(route('career-profile.documents.download', $document))
            ->assertForbidden();

        $this->actingAs($other)
            ->post(route('career-profile.import.confirm', $document), ['sections' => ['skills' => [['name' => 'Hacked']]]])
            ->assertForbidden();

        $this->actingAs($other)
            ->delete(route('career-profile.documents.destroy', $document))
            ->assertForbidden();
    }

    public function test_confirm_applies_selected_sections_and_keeps_existing_data(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $profile = $user->careerProfile()->create();
        $profile->experiences()->create([
            'organization' => 'Existing Co',
            'job_title' => 'Old Role',
            'sort_order' => 0,
        ]);

        $document = $this->createDocument($user);
        $document->update([
            'extraction_status' => ProfileDocument::STATUS_COMPLETED,
            'draft' => $this->sampleDraft(),
        ]);

        $this->actingAs($user)
            ->post(route('career-profile.import.confirm', $document), [
                'sections' => [
                    'contact' => ['full_name' => 'John Doe', 'email' => 'john@example.com'],
                    'experiences' => [[
                        'organization' => 'Acme',
                        'job_title' => 'Engineer',
                        'start_date' => '2023-01-01',
                        'is_current' => true,
                    ]],
                    'skills' => [['name' => 'PHP']],
                ],
            ])
            ->assertRedirect(route('career-profile.show'))
            ->assertSessionHas('success');

        // Contact written.
        $this->assertDatabaseHas('profile_contacts', [
            'career_profile_id' => $profile->id,
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Existing row untouched, new row appended after it.
        $this->assertDatabaseHas('profile_experiences', ['organization' => 'Existing Co', 'sort_order' => 0]);
        $this->assertDatabaseHas('profile_experiences', ['organization' => 'Acme', 'sort_order' => 1]);

        // Unselected sections (educations/projects/certifications) were not written.
        $this->assertDatabaseCount('profile_educations', 0);
        $this->assertDatabaseCount('profile_projects', 0);
        $this->assertDatabaseCount('profile_certifications', 0);

        $this->assertNotNull($document->fresh()->applied_at);
    }

    public function test_confirm_rejects_missing_organization(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $document = $this->createDocument($user);
        $document->update([
            'extraction_status' => ProfileDocument::STATUS_COMPLETED,
            'draft' => $this->sampleDraft(),
        ]);

        $this->actingAs($user)
            ->post(route('career-profile.import.confirm', $document), [
                'sections' => ['experiences' => [['job_title' => 'Engineer']]],
            ]);

        // Row without organization is dropped, nothing else written.
        $this->assertDatabaseCount('profile_experiences', 0);
    }

    public function test_delete_removes_record_and_source_file(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        Storage::disk('local')->put('cv-uploads/cv.pdf', $this->pdfBytes());
        $document = $this->createDocument($user);

        $this->actingAs($user)
            ->delete(route('career-profile.documents.destroy', $document))
            ->assertRedirect();

        $this->assertDatabaseCount('profile_documents', 0);
        Storage::disk('local')->assertMissing('cv-uploads/cv.pdf');
    }
}
