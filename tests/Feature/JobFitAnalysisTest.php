<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeJobApplicationJob;
use App\Models\JobApplication;
use App\Models\JobRequirementAnalysis;
use App\Models\User;
use App\Services\Ai\JobFitAnalysisProvider;
use App\Services\ProfileSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobFitAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private const JD = 'We are looking for a Senior Software Engineer to build and maintain our online examination platform. You will design APIs, review code, and mentor junior engineers. Requirements: 5+ years PHP, strong Laravel and MySQL, experience with React or similar, good communication in English.';

    private function setupApplication(User $user): JobApplication
    {
        $profile = $user->careerProfile()->create();
        $profile->experiences()->create(['organization' => 'Acme', 'job_title' => 'Dev', 'sort_order' => 0]);
        $profile->skills()->create(['name' => 'PHP', 'sort_order' => 0]);

        return JobApplication::create([
            'user_id' => $user->id,
            'career_profile_id' => $profile->id,
            'company_name' => 'Acme Corp',
            'job_title' => 'Senior Software Engineer',
            'job_description' => self::JD,
            'language' => 'id',
        ]);
    }

    /** Provider stub returning a fixed payload. */
    private function stubProvider(array $payload): JobFitAnalysisProvider
    {
        return new class($payload) implements JobFitAnalysisProvider
        {
            public function __construct(private array $payload) {}

            public function name(): string
            {
                return 'stub';
            }

            public function analyze(array $profileSnapshot, array $jobSnapshot): array
            {
                return $this->payload;
            }
        };
    }

    private function analysisFor(JobApplication $application): JobRequirementAnalysis
    {
        $snapshots = app(ProfileSnapshotService::class);

        return $application->analyses()->create([
            'status' => JobRequirementAnalysis::STATUS_PENDING,
            'profile_snapshot' => $snapshots->build($application->careerProfile),
            'job_snapshot' => [
                'company_name' => $application->company_name,
                'job_title' => $application->job_title,
                'job_description' => $application->job_description,
                'language' => $application->language,
            ],
        ]);
    }

    private function validPayload(int $experienceId, int $skillId): array
    {
        return [
            'requirements' => [
                [
                    'text' => '5+ years PHP experience',
                    'category' => 'required_qualification',
                    'priority' => 'must_have',
                    'match_status' => 'matched',
                    'evidence_refs' => ["experience:{$experienceId}", "skill:{$skillId}"],
                    'recommendation' => null,
                ],
                [
                    'text' => 'Experience with React',
                    'category' => 'skill',
                    'priority' => 'nice_to_have',
                    'match_status' => 'not_evidenced',
                    'evidence_refs' => [],
                    'recommendation' => 'Tambahkan proyek React yang pernah Anda kerjakan ke profil.',
                ],
            ],
            'keyword_suggestions' => [
                ['keyword' => 'PHP', 'supported_by_profile' => true, 'evidence_refs' => ["skill:{$skillId}"]],
                ['keyword' => 'React', 'supported_by_profile' => false, 'evidence_refs' => []],
            ],
            'seniority' => 'Senior',
            'summary' => 'Kandidat memenuhi persyaratan utama; satu skill belum ada bukti.',
            'demoted' => 0,
        ];
    }

    public function test_job_completes_with_stub_provider(): void
    {
        $user = User::factory()->create();
        $application = $this->setupApplication($user);
        $analysis = $this->analysisFor($application);

        $profile = $application->careerProfile;
        $experienceId = $profile->experiences()->first()->id;
        $skillId = $profile->skills()->first()->id;

        (new AnalyzeJobApplicationJob($analysis->id))
            ->handle($this->stubProvider($this->validPayload($experienceId, $skillId)), app(ProfileSnapshotService::class));

        $analysis->refresh();

        $this->assertSame(JobRequirementAnalysis::STATUS_COMPLETED, $analysis->status);
        $this->assertSame('stub', explode(':', $analysis->model_name)[0]);
        $this->assertCount(2, $analysis->analysis_json['requirements']);

        $counts = $analysis->counts();
        $this->assertSame(1, $counts['matched']);
        $this->assertSame(0, $counts['partial']);
        $this->assertSame(1, $counts['not_evidenced']);
        $this->assertSame(2, $counts['total']);
    }

    public function test_fabricated_evidence_reference_is_dropped_and_demoted(): void
    {
        $user = User::factory()->create();
        $application = $this->setupApplication($user);
        $analysis = $this->analysisFor($application);

        // The model claims a match citing rows that do not exist.
        $payload = $this->validPayload(999, 999);
        $payload['requirements'] = [[
            'text' => 'Kubernetes in production',
            'category' => 'tool',
            'priority' => 'must_have',
            'match_status' => 'matched',
            'evidence_refs' => ['experience:999', 'skill:999'],
            'recommendation' => null,
        ]];

        (new AnalyzeJobApplicationJob($analysis->id))
            ->handle($this->stubProvider($payload), app(ProfileSnapshotService::class));

        $analysis->refresh();

        $this->assertSame(JobRequirementAnalysis::STATUS_COMPLETED, $analysis->status);

        $requirement = $analysis->analysis_json['requirements'][0];
        $this->assertSame([], $requirement['evidence_refs']);
        $this->assertSame('not_evidenced', $requirement['match_status']);
        $this->assertSame(0, $analysis->counts()['matched']);
    }

    public function test_keyword_support_is_forced_by_real_evidence(): void
    {
        $user = User::factory()->create();
        $application = $this->setupApplication($user);
        $analysis = $this->analysisFor($application);

        $payload = $this->validPayload(0, 0);
        // Model claims support but cites nothing real.
        $payload['keyword_suggestions'] = [
            ['keyword' => 'Laravel', 'supported_by_profile' => true, 'evidence_refs' => ['skill:999']],
        ];

        (new AnalyzeJobApplicationJob($analysis->id))
            ->handle($this->stubProvider($payload), app(ProfileSnapshotService::class));

        $keyword = $analysis->fresh()->analysis_json['keyword_suggestions'][0];

        $this->assertFalse($keyword['supported_by_profile']);
        $this->assertSame([], $keyword['evidence_refs']);
    }

    public function test_empty_profile_fails_with_clear_code(): void
    {
        $user = User::factory()->create();
        $profile = $user->careerProfile()->create(); // no data at all

        $application = JobApplication::create([
            'user_id' => $user->id,
            'career_profile_id' => $profile->id,
            'company_name' => 'Acme',
            'job_title' => 'Dev',
            'job_description' => self::JD,
            'language' => 'id',
        ]);

        $analysis = $this->analysisFor($application);

        (new AnalyzeJobApplicationJob($analysis->id))
            ->handle($this->stubProvider($this->validPayload(1, 1)), app(ProfileSnapshotService::class));

        $analysis->refresh();

        $this->assertSame(JobRequirementAnalysis::STATUS_FAILED, $analysis->status);
        $this->assertSame('empty_profile', $analysis->error_code);
        $this->assertNotNull($analysis->errorMessage());
    }

    public function test_provider_failure_marks_failed_without_leaking_details(): void
    {
        $user = User::factory()->create();
        $application = $this->setupApplication($user);
        $analysis = $this->analysisFor($application);

        $failing = new class implements JobFitAnalysisProvider
        {
            public function name(): string
            {
                return 'failing';
            }

            public function analyze(array $profileSnapshot, array $jobSnapshot): array
            {
                throw new \RuntimeException('provider_unavailable');
            }
        };

        (new AnalyzeJobApplicationJob($analysis->id))->handle($failing, app(ProfileSnapshotService::class));

        $analysis->refresh();

        $this->assertSame(JobRequirementAnalysis::STATUS_FAILED, $analysis->status);
        $this->assertSame('provider_unavailable', $analysis->error_code);
        $this->assertNull($analysis->analysis_json);
        $this->assertStringNotContainsString('RuntimeException', (string) $analysis->errorMessage());
    }

    public function test_status_endpoint_returns_counts_for_owner(): void
    {
        $user = User::factory()->create();
        $application = $this->setupApplication($user);
        $analysis = $this->analysisFor($application);
        $analysis->update([
            'status' => JobRequirementAnalysis::STATUS_COMPLETED,
            'analysis_json' => ['requirements' => [
                ['text' => 'A', 'category' => 'skill', 'priority' => 'must_have', 'match_status' => 'matched', 'evidence_refs' => ['skill:1'], 'recommendation' => null],
                ['text' => 'B', 'category' => 'skill', 'priority' => 'must_have', 'match_status' => 'partial', 'evidence_refs' => ['skill:1'], 'recommendation' => 'x'],
            ]],
        ]);

        $this->actingAs($user)
            ->getJson(route('applications.analysis.status', $application))
            ->assertOk()
            ->assertJson([
                'status' => 'completed',
                'matched' => 1,
                'partial' => 1,
                'not_evidenced' => 0,
                'total' => 2,
            ]);
    }

    public function test_show_page_exposes_result_only_when_completed(): void
    {
        $user = User::factory()->create();
        $application = $this->setupApplication($user);

        $analysis = $this->analysisFor($application);

        $this->actingAs($user)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Applications/Show')
                ->where('analysis.status', 'pending')
                ->where('analysis.result', null));

        $analysis->update([
            'status' => JobRequirementAnalysis::STATUS_COMPLETED,
            'analysis_json' => ['requirements' => [], 'keyword_suggestions' => [], 'summary' => 'ok'],
        ]);

        $this->actingAs($user)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('analysis.result.summary', 'ok'));
    }
}
