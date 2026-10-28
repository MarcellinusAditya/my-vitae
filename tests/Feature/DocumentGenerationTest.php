<?php

namespace Tests\Feature;

use App\Jobs\GenerateDocumentJob;
use App\Models\AiUsageLog;
use App\Models\GeneratedDocument;
use App\Models\JobApplication;
use App\Models\JobRequirementAnalysis;
use App\Models\User;
use App\Services\Ai\AiUsageLogger;
use App\Services\Ai\DocumentGenerationProvider;
use App\Services\DocumentGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DocumentGenerationTest extends TestCase
{
    use RefreshDatabase;

    private const JD = 'We are hiring a Senior Backend Engineer to design and scale payment APIs. Requirements: 5+ years PHP, strong Laravel, MySQL performance tuning, and mentoring junior engineers.';

    private function application(User $user): JobApplication
    {
        $profile = $user->careerProfile()->create(['summary' => 'Backend engineer.', 'writing_style' => 'professional']);
        $profile->skills()->create(['name' => 'PHP', 'category' => 'Backend', 'sort_order' => 0]);
        $profile->experiences()->create([
            'organization' => 'Acme Corp', 'job_title' => 'Senior Engineer',
            'start_date' => '2022-01-01', 'is_current' => true,
            'description' => 'Led a team of 4 engineers.', 'sort_order' => 0,
        ]);

        return JobApplication::create([
            'user_id' => $user->id,
            'career_profile_id' => $profile->id,
            'company_name' => 'Global Fintech',
            'job_title' => 'Senior Backend Engineer',
            'job_description' => self::JD,
            'language' => 'id',
        ]);
    }

    private function completedAnalysis(JobApplication $application): JobRequirementAnalysis
    {
        return $application->analyses()->create([
            'status' => JobRequirementAnalysis::STATUS_COMPLETED,
            'analysis_json' => [
                'requirements' => [[
                    'text' => 'Strong PHP', 'category' => 'skill', 'priority' => 'must_have',
                    'match_status' => 'matched', 'evidence_refs' => ['skill:1'], 'recommendation' => null,
                ]],
                'keyword_suggestions' => [],
                'summary' => 'Good coverage.',
            ],
            'profile_snapshot' => ['experiences' => [], 'skills' => []],
            'job_snapshot' => ['job_description' => self::JD],
        ]);
    }

    private function stubProvider(array $resume, ?array $coverLetter = null): DocumentGenerationProvider
    {
        return new class($resume, $coverLetter) implements DocumentGenerationProvider
        {
            public function __construct(private array $resume, private ?array $coverLetter) {}

            public function name(): string
            {
                return 'stub';
            }

            public function generateResume(array $profileSnapshot, array $jobSnapshot, array $preferences): array
            {
                return $this->resume;
            }

            public function generateCoverLetter(array $profileSnapshot, array $jobSnapshot, array $preferences): array
            {
                return $this->coverLetter ?? ['opening' => 'Hi', 'body_paragraphs' => [], 'recipient_line' => 'Dear Hiring Manager,'];
            }
        };
    }

    private function resumePayload(): array
    {
        return [
            'summary' => 'Backend engineer focused on PHP.',
            'skills' => [['name' => 'PHP', 'category' => 'Backend', 'evidence_refs' => ['skill:1']]],
            'experiences' => [[
                'organization' => 'Acme Corp', 'role' => 'Senior Engineer',
                'start_date' => '2022-01-01', 'end_date' => null, 'is_current' => true,
                'bullets' => ['Led a team of 4 engineers.'], 'evidence_refs' => ['experience:1'],
            ]],
            'projects' => [], 'education' => [], 'certifications' => [], 'languages' => ['Indonesian'],
            'unverified_claims' => [],
        ];
    }

    public function test_generation_requires_completed_analysis(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $application = $this->application($user);

        // No analysis yet.
        $this->actingAs($user)
            ->post(route('documents.store', $application), ['type' => 'resume'])
            ->assertRedirect();

        $document = GeneratedDocument::firstOrFail();

        (new GenerateDocumentJob($document->id))->handle(
            $this->stubProvider($this->resumePayload()),
            app(DocumentGenerationService::class),
            app(AiUsageLogger::class),
        );

        $this->assertSame(GeneratedDocument::STATUS_FAILED, $document->fresh()->generation_status);
        $this->assertSame('analysis_required', $document->fresh()->error_code);
    }

    public function test_store_creates_document_and_dispatches_job(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $application = $this->application($user);
        $this->completedAnalysis($application);

        $this->actingAs($user)
            ->post(route('documents.store', $application), ['type' => 'resume'])
            ->assertRedirect();

        $document = GeneratedDocument::firstOrFail();

        $this->assertSame(GeneratedDocument::TYPE_RESUME, $document->type);
        $this->assertSame(1, $document->version);
        $this->assertSame(GeneratedDocument::STATUS_PENDING, $document->generation_status);
        Queue::assertPushed(GenerateDocumentJob::class, fn ($job) => $job->documentId === $document->id);
    }

    public function test_job_completes_and_writes_usage_log(): void
    {
        $user = User::factory()->create();
        $application = $this->application($user);
        $analysis = $this->completedAnalysis($application);

        $document = $application->generatedDocuments()->create([
            'job_requirement_analysis_id' => $analysis->id,
            'type' => GeneratedDocument::TYPE_RESUME,
            'title' => 'CV test',
            'version' => 1,
            'language' => 'id',
            'generation_status' => GeneratedDocument::STATUS_PENDING,
        ]);

        (new GenerateDocumentJob($document->id))->handle(
            $this->stubProvider($this->resumePayload()),
            app(DocumentGenerationService::class),
            app(AiUsageLogger::class),
        );

        $document->refresh();

        $this->assertSame(GeneratedDocument::STATUS_COMPLETED, $document->generation_status);
        $this->assertSame('Backend engineer focused on PHP.', $document->content_json['summary']);
        $this->assertNotNull($document->model_name);

        $log = AiUsageLog::firstOrFail();
        $this->assertSame('resume_generation', $log->operation);
        $this->assertSame(AiUsageLog::STATUS_SUCCESS, $log->status);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($application->id, $log->job_application_id);
        // No prompt or CV content is stored.
        $this->assertNull($log->getAttribute('prompt'));
    }

    public function test_regenerate_creates_a_new_version_and_keeps_the_old_one(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $application = $this->application($user);
        $analysis = $this->completedAnalysis($application);

        $existing = $application->generatedDocuments()->create([
            'job_requirement_analysis_id' => $analysis->id,
            'type' => GeneratedDocument::TYPE_RESUME,
            'title' => 'CV v1',
            'version' => 1,
            'language' => 'id',
            'generation_status' => GeneratedDocument::STATUS_COMPLETED,
            'content_json' => ['summary' => 'Old summary'],
        ]);

        $this->actingAs($user)
            ->post(route('documents.regenerate', $existing), [])
            ->assertRedirect();

        $this->assertSame(2, GeneratedDocument::count());

        $new = GeneratedDocument::where('id', '!=', $existing->id)->firstOrFail();
        $this->assertSame(2, $new->version);
        $this->assertSame(GeneratedDocument::STATUS_PENDING, $new->generation_status);

        // Old version untouched.
        $this->assertSame('Old summary', $existing->fresh()->content_json['summary']);
        $this->assertSame(GeneratedDocument::STATUS_COMPLETED, $existing->fresh()->generation_status);
    }

    public function test_user_can_edit_and_save_document_content(): void
    {
        $user = User::factory()->create();
        $application = $this->application($user);
        $analysis = $this->completedAnalysis($application);

        $document = $application->generatedDocuments()->create([
            'job_requirement_analysis_id' => $analysis->id,
            'type' => GeneratedDocument::TYPE_RESUME,
            'title' => 'CV',
            'version' => 1,
            'language' => 'id',
            'generation_status' => GeneratedDocument::STATUS_COMPLETED,
            'content_json' => ['summary' => 'Original', 'unverified_claims' => ['Angka "99" tidak ditemukan di profil']],
        ]);

        $this->actingAs($user)
            ->patch(route('documents.update', $document), [
                'content' => ['summary' => 'Edited by the user'],
            ])
            ->assertRedirect();

        $fresh = $document->fresh();
        $this->assertSame('Edited by the user', $fresh->content_json['summary']);
        // Guardrail metadata survives a manual edit.
        $this->assertSame(['Angka "99" tidak ditemukan di profil'], $fresh->unverifiedClaimList());
    }

    public function test_index_and_show_are_scoped_to_the_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $other->careerProfile()->create();

        $application = $this->application($owner);
        $analysis = $this->completedAnalysis($application);
        $document = $application->generatedDocuments()->create([
            'job_requirement_analysis_id' => $analysis->id,
            'type' => GeneratedDocument::TYPE_COVER_LETTER,
            'title' => 'CL',
            'version' => 1,
            'language' => 'id',
            'generation_status' => GeneratedDocument::STATUS_COMPLETED,
            'content_json' => ['opening' => 'Hello'],
        ]);

        $this->actingAs($owner)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Documents/Index')->has('documents', 1));

        $this->actingAs($owner)
            ->get(route('documents.show', $document))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Documents/Show')->where('document.id', $document->id));

        $this->actingAs($other)->get(route('documents.index'))->assertOk()->assertInertia(fn ($page) => $page->has('documents', 0));
        $this->actingAs($other)->get(route('documents.show', $document))->assertForbidden();
        $this->actingAs($other)->patch(route('documents.update', $document), ['content' => ['summary' => 'hack']])->assertForbidden();
        $this->actingAs($other)->post(route('documents.regenerate', $document))->assertForbidden();
        $this->actingAs($other)->delete(route('documents.destroy', $document))->assertForbidden();
    }

    public function test_cover_letter_generation_sets_neutral_recipient(): void
    {
        $user = User::factory()->create();
        $application = $this->application($user);
        $analysis = $this->completedAnalysis($application);

        $document = $application->generatedDocuments()->create([
            'job_requirement_analysis_id' => $analysis->id,
            'type' => GeneratedDocument::TYPE_COVER_LETTER,
            'title' => 'CL',
            'version' => 1,
            'language' => 'id',
            'tone' => 'professional',
            'generation_status' => GeneratedDocument::STATUS_PENDING,
        ]);

        $payload = [
            'recipient_line' => 'Dear Mr. Fake Recruiter,',
            'opening' => 'Saya melamar posisi ini.',
            'body_paragraphs' => [['text' => 'Di Acme Corp saya memimpin tim.', 'evidence_refs' => ['experience:1']]],
            'closing' => 'Terima kasih.',
            'signature' => 'Jane Doe',
        ];

        (new GenerateDocumentJob($document->id))->handle(
            $this->stubProvider($this->resumePayload(), $payload),
            app(DocumentGenerationService::class),
            app(AiUsageLogger::class),
        );

        $result = $document->fresh()->content_json;

        $this->assertSame('Kepada Tim Rekrutmen,', $result['recipient_line']);
        $this->assertSame('cover_letter_generation', AiUsageLog::first()->operation);
    }

    public function test_provider_failure_marks_failed_and_logs_it(): void
    {
        $user = User::factory()->create();
        $application = $this->application($user);
        $analysis = $this->completedAnalysis($application);

        $document = $application->generatedDocuments()->create([
            'job_requirement_analysis_id' => $analysis->id,
            'type' => GeneratedDocument::TYPE_RESUME,
            'title' => 'CV',
            'version' => 1,
            'language' => 'id',
            'generation_status' => GeneratedDocument::STATUS_PENDING,
        ]);

        $failing = new class implements DocumentGenerationProvider
        {
            public function name(): string
            {
                return 'failing';
            }

            public function generateResume(array $profileSnapshot, array $jobSnapshot, array $preferences): array
            {
                throw new \RuntimeException('provider_unavailable');
            }

            public function generateCoverLetter(array $profileSnapshot, array $jobSnapshot, array $preferences): array
            {
                throw new \RuntimeException('provider_unavailable');
            }
        };

        (new GenerateDocumentJob($document->id))->handle(
            $failing,
            app(DocumentGenerationService::class),
            app(AiUsageLogger::class),
        );

        $fresh = $document->fresh();

        $this->assertSame(GeneratedDocument::STATUS_FAILED, $fresh->generation_status);
        $this->assertSame('provider_unavailable', $fresh->error_code);
        $this->assertNull($fresh->content_json);

        $log = AiUsageLog::firstOrFail();
        $this->assertSame(AiUsageLog::STATUS_FAILED, $log->status);
        $this->assertSame('provider_unavailable', $log->error_code);
    }

    public function test_status_endpoint_reports_progress(): void
    {
        $user = User::factory()->create();
        $application = $this->application($user);
        $analysis = $this->completedAnalysis($application);

        $document = $application->generatedDocuments()->create([
            'job_requirement_analysis_id' => $analysis->id,
            'type' => GeneratedDocument::TYPE_RESUME,
            'title' => 'CV',
            'version' => 1,
            'language' => 'id',
            'generation_status' => GeneratedDocument::STATUS_PROCESSING,
        ]);

        $this->actingAs($user)
            ->getJson(route('documents.status', $document))
            ->assertOk()
            ->assertJson(['status' => 'processing', 'error_message' => null]);
    }

    public function test_deleting_document_returns_to_application(): void
    {
        $user = User::factory()->create();
        $application = $this->application($user);
        $analysis = $this->completedAnalysis($application);

        $document = $application->generatedDocuments()->create([
            'job_requirement_analysis_id' => $analysis->id,
            'type' => GeneratedDocument::TYPE_RESUME,
            'title' => 'CV',
            'version' => 1,
            'language' => 'id',
            'generation_status' => GeneratedDocument::STATUS_COMPLETED,
        ]);

        $this->actingAs($user)
            ->delete(route('documents.destroy', $document))
            ->assertRedirect(route('applications.show', $application));

        $this->assertDatabaseCount('generated_documents', 0);
    }
}
