<?php

namespace Tests\Feature;

use App\Models\GeneratedDocument;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\DocumentExportService;
use App\Services\UserDataCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression: deleting an application or account must remove export files.
 *
 * Eloquent model events do not fire for query-builder deletes, and a DB cascade
 * cannot touch the filesystem, so deletion goes through UserDataCleanupService.
 */
class DocumentExportCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function documentWithExport(User $user): array
    {
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

        $document = $application->generatedDocuments()->create([
            'type' => GeneratedDocument::TYPE_RESUME,
            'title' => 'CV',
            'version' => 1,
            'language' => 'id',
            'generation_status' => GeneratedDocument::STATUS_COMPLETED,
            'content_json' => [
                'summary' => 'Engineer summary.',
                'skills' => [['name' => 'PHP']],
                'experiences' => [],
            ],
        ]);

        $export = app(DocumentExportService::class)->create($document, 'pdf');

        return [$application, $document, $export];
    }

    public function test_deleting_application_endpoint_removes_export_files(): void
    {
        $user = User::factory()->create();
        [$application, , $export] = $this->documentWithExport($user);

        $path = $export->storage_path;
        Storage::disk('local')->assertExists($path);

        $this->actingAs($user)
            ->delete(route('applications.destroy', $application))
            ->assertRedirect(route('applications.index'));

        $this->assertDatabaseCount('document_exports', 0);
        $this->assertDatabaseCount('generated_documents', 0);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_cleanup_service_removes_everything_for_an_application(): void
    {
        $user = User::factory()->create();
        [$application, , $export] = $this->documentWithExport($user);

        $path = $export->storage_path;

        app(UserDataCleanupService::class)->deleteApplication($application);

        $this->assertDatabaseCount('document_exports', 0);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_account_deletion_removes_files(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-password')]);
        [, , $export] = $this->documentWithExport($user);

        $path = $export->storage_path;

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'secret-password'])
            ->assertRedirect('/');

        $this->assertDatabaseCount('document_exports', 0);
        $this->assertDatabaseCount('users', 0);
        Storage::disk('local')->assertMissing($path);
    }
}
