<?php

namespace Tests\Feature;

use App\Models\DocumentExport;
use App\Models\GeneratedDocument;
use App\Models\JobApplication;
use App\Models\ProfileDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneFilesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function oldProfileDocument(User $user, string $path, string $status = ProfileDocument::STATUS_COMPLETED): ProfileDocument
    {
        $document = $user->documents()->create([
            'original_filename' => 'cv.pdf',
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'extraction_status' => $status,
        ]);

        DB::table('profile_documents')->where('id', $document->id)->update(['created_at' => now()->subDays(40)]);

        return $document->fresh();
    }

    private function oldExport(User $user): DocumentExport
    {
        $profile = $user->careerProfile()->create(['summary' => 'Engineer.']);
        $application = JobApplication::create([
            'user_id' => $user->id,
            'career_profile_id' => $profile->id,
            'company_name' => 'Acme',
            'job_title' => 'Dev',
            'job_description' => str_repeat('text ', 20),
            'language' => 'id',
        ]);
        $document = $application->generatedDocuments()->create([
            'type' => GeneratedDocument::TYPE_RESUME,
            'title' => 'CV',
            'version' => 1,
            'language' => 'id',
            'generation_status' => GeneratedDocument::STATUS_COMPLETED,
            'content_json' => ['summary' => 'S', 'skills' => [], 'experiences' => []],
        ]);

        $export = $document->exports()->create([
            'format' => 'pdf',
            'filename' => 'cv.pdf',
            'storage_path' => 'document-exports/'.$document->id.'/cv.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
        ]);

        Storage::disk('local')->put($export->storage_path, 'pdf-bytes');
        DB::table('document_exports')->where('id', $export->id)->update(['created_at' => now()->subDays(40)]);

        return $export->fresh();
    }

    public function test_old_completed_source_files_are_removed(): void
    {
        $user = User::factory()->create();
        $document = $this->oldProfileDocument($user, 'cv-uploads/old.pdf');
        Storage::disk('local')->put('cv-uploads/old.pdf', 'pdf');

        $this->artisan('app:prune-files')->assertExitCode(0);

        Storage::disk('local')->assertMissing('cv-uploads/old.pdf');
        $this->assertDatabaseMissing('profile_documents', ['id' => $document->id]);
    }

    public function test_recent_files_are_untouched(): void
    {
        $user = User::factory()->create();
        $document = $user->documents()->create([
            'original_filename' => 'new.pdf',
            'storage_path' => 'cv-uploads/new.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'extraction_status' => ProfileDocument::STATUS_COMPLETED,
        ]);
        Storage::disk('local')->put('cv-uploads/new.pdf', 'pdf');

        $this->artisan('app:prune-files')->assertExitCode(0);

        Storage::disk('local')->assertExists('cv-uploads/new.pdf');
        $this->assertDatabaseHas('profile_documents', ['id' => $document->id, 'storage_path' => 'cv-uploads/new.pdf']);
    }

    public function test_pending_uploads_are_not_pruned(): void
    {
        $user = User::factory()->create();
        $this->oldProfileDocument($user, 'cv-uploads/pending.pdf', ProfileDocument::STATUS_PENDING);
        Storage::disk('local')->put('cv-uploads/pending.pdf', 'pdf');

        $this->artisan('app:prune-files')->assertExitCode(0);

        // Still processing: the file may still be needed.
        Storage::disk('local')->assertExists('cv-uploads/pending.pdf');
    }

    public function test_old_exports_are_removed(): void
    {
        $user = User::factory()->create();
        $export = $this->oldExport($user);

        $this->artisan('app:prune-files')->assertExitCode(0);

        Storage::disk('local')->assertMissing($export->storage_path);
        $this->assertDatabaseMissing('document_exports', ['id' => $export->id]);
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $user = User::factory()->create();
        $document = $this->oldProfileDocument($user, 'cv-uploads/old.pdf');
        Storage::disk('local')->put('cv-uploads/old.pdf', 'pdf');

        $this->artisan('app:prune-files', ['--dry-run' => true])->assertExitCode(0);

        Storage::disk('local')->assertExists('cv-uploads/old.pdf');
        $this->assertDatabaseHas('profile_documents', ['id' => $document->id, 'storage_path' => 'cv-uploads/old.pdf']);
    }

    public function test_old_failed_jobs_are_removed(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) str()->uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'boom',
            'failed_at' => now()->subDays(40),
        ]);

        $this->artisan('app:prune-files')->assertExitCode(0);

        $this->assertDatabaseCount('failed_jobs', 0);
    }
}
