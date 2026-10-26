<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeJobApplicationJob;
use App\Models\JobApplication;
use App\Models\JobRequirementAnalysis;
use App\Models\User;
use App\Services\CareerProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class JobApplicationTest extends TestCase
{
    use RefreshDatabase;

    private const JD = 'We are looking for a Senior Software Engineer to build and maintain our online examination platform. You will design APIs, review code, and mentor junior engineers. Requirements: 5+ years PHP, strong Laravel and MySQL, experience with React or similar, good communication in English.';

    private function applicationFor(User $user, array $overrides = []): JobApplication
    {
        $profile = app(CareerProfileService::class)->forUser($user);

        return JobApplication::create([
            'user_id' => $user->id,
            'career_profile_id' => $profile->id,
            'company_name' => 'Acme Corp',
            'job_title' => 'Senior Software Engineer',
            'job_location' => 'Jakarta',
            'job_description' => self::JD,
            'language' => 'id',
            'status' => JobApplication::STATUS_DRAFT,
            ...$overrides,
        ]);
    }

    public function test_index_requires_authentication_and_loads(): void
    {
        $this->get('/applications')->assertRedirect('/login');

        $user = User::factory()->create();
        $this->applicationFor($user);

        $this->actingAs($user)
            ->get('/applications')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Applications/Index')
                ->has('applications', 1)
                ->where('counts.total', 1));
    }

    public function test_create_page_loads(): void
    {
        $user = User::factory()->create();
        $user->careerProfile()->create();

        $this->actingAs($user)
            ->get('/applications/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Applications/Create')
                ->where('defaultLanguage', 'id'));
    }

    public function test_store_creates_application_and_queues_analysis(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $user->careerProfile()->create();

        $response = $this->actingAs($user)->post('/applications', [
            'company_name' => 'Acme Corp',
            'job_title' => 'Senior Software Engineer',
            'job_location' => 'Jakarta',
            'job_description' => self::JD,
            'language' => 'id',
        ]);

        $application = JobApplication::firstOrFail();

        $response->assertRedirect(route('applications.show', $application));

        $this->assertSame(JobApplication::STATUS_DRAFT, $application->status);
        $this->assertSame($user->id, $application->user_id);
        $this->assertDatabaseHas('job_requirement_analyses', [
            'job_application_id' => $application->id,
            'status' => JobRequirementAnalysis::STATUS_PENDING,
        ]);
        Queue::assertPushed(AnalyzeJobApplicationJob::class);
    }

    public function test_store_rejects_too_short_job_description(): void
    {
        $user = User::factory()->create();
        $user->careerProfile()->create();

        $this->actingAs($user)
            ->post('/applications', [
                'company_name' => 'Acme',
                'job_title' => 'Dev',
                'job_description' => 'too short',
                'language' => 'id',
            ])
            ->assertSessionHasErrors('job_description');

        $this->assertDatabaseCount('job_applications', 0);
    }

    public function test_snapshot_is_taken_at_analysis_time_and_survives_profile_edit(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $profile = $user->careerProfile()->create();
        $profile->experiences()->create(['organization' => 'Old Co', 'job_title' => 'Dev', 'sort_order' => 0]);
        $profile->skills()->create(['name' => 'PHP', 'sort_order' => 0]);

        $this->actingAs($user)->post('/applications', [
            'company_name' => 'Acme',
            'job_title' => 'Dev',
            'job_description' => self::JD,
            'language' => 'id',
        ]);

        $analysis = JobRequirementAnalysis::firstOrFail();
        $snapshot = $analysis->profile_snapshot;

        $this->assertSame('Old Co', $snapshot['experiences'][0]['organization']);
        $this->assertSame('PHP', $snapshot['skills'][0]['name']);

        // Edit the profile after the analysis was queued.
        $profile->update(['summary' => 'Changed later']);
        $profile->skills()->create(['name' => 'Rust', 'sort_order' => 1]);

        $fresh = $analysis->fresh();

        $this->assertSame('Old Co', $fresh->profile_snapshot['experiences'][0]['organization']);
        $this->assertNull($fresh->profile_snapshot['summary'] ?? null);
        $this->assertCount(1, $fresh->profile_snapshot['skills']);
    }

    public function test_duplicate_copies_content_without_analyses(): void
    {
        $user = User::factory()->create();
        $application = $this->applicationFor($user);
        $application->analyses()->create(['status' => JobRequirementAnalysis::STATUS_COMPLETED]);

        $this->actingAs($user)
            ->post(route('applications.duplicate', $application))
            ->assertRedirect();

        $copy = JobApplication::where('id', '!=', $application->id)->firstOrFail();

        $this->assertSame($application->company_name, $copy->company_name);
        $this->assertSame($application->job_description, $copy->job_description);
        $this->assertSame(JobApplication::STATUS_DRAFT, $copy->status);
        $this->assertSame(0, $copy->analyses()->count());
    }

    public function test_update_status(): void
    {
        $user = User::factory()->create();
        $application = $this->applicationFor($user);

        $this->actingAs($user)
            ->patch(route('applications.status', $application), ['status' => 'interview'])
            ->assertRedirect();

        $this->assertSame('interview', $application->fresh()->status);

        $this->actingAs($user)
            ->patch(route('applications.status', $application), ['status' => 'bogus'])
            ->assertSessionHasErrors('status');
    }

    public function test_edit_and_update(): void
    {
        $user = User::factory()->create();
        $application = $this->applicationFor($user);

        $this->actingAs($user)
            ->get(route('applications.edit', $application))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Applications/Edit'));

        $this->actingAs($user)
            ->patch(route('applications.update', $application), [
                'company_name' => 'Acme Corp Updated',
                'job_title' => 'Staff Engineer',
                'job_description' => self::JD.' Extra details here to pass validation.',
                'language' => 'en',
            ])
            ->assertRedirect(route('applications.show', $application));

        $fresh = $application->fresh();
        $this->assertSame('Acme Corp Updated', $fresh->company_name);
        $this->assertSame('en', $fresh->language);
    }

    public function test_destroy_removes_application(): void
    {
        $user = User::factory()->create();
        $application = $this->applicationFor($user);

        $this->actingAs($user)
            ->delete(route('applications.destroy', $application))
            ->assertRedirect(route('applications.index'));

        $this->assertDatabaseCount('job_applications', 0);
        $this->assertDatabaseCount('job_requirement_analyses', 0);
    }

    public function test_other_user_cannot_access_any_operation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $other->careerProfile()->create();

        $application = $this->applicationFor($owner);

        $this->actingAs($other)->get(route('applications.show', $application))->assertForbidden();
        $this->actingAs($other)->get(route('applications.edit', $application))->assertForbidden();
        $this->actingAs($other)->patch(route('applications.update', $application), [])->assertForbidden();
        $this->actingAs($other)->patch(route('applications.status', $application), ['status' => 'applied'])->assertForbidden();
        $this->actingAs($other)->post(route('applications.analyze', $application))->assertForbidden();
        $this->actingAs($other)->get(route('applications.analysis.status', $application))->assertForbidden();
        $this->actingAs($other)->post(route('applications.duplicate', $application))->assertForbidden();
        $this->actingAs($other)->delete(route('applications.destroy', $application))->assertForbidden();
    }

    public function test_index_filter_and_list_never_leak_other_users_applications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->applicationFor($other, ['company_name' => 'Secret Corp']);

        $mine = $this->applicationFor($user, ['status' => 'applied']);

        $this->actingAs($user)
            ->get('/applications?status=applied')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('applications', 1)
                ->where('applications.0.id', $mine->id));

        $this->actingAs($user)
            ->get('/applications?status=draft')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('applications', 0));
    }

    public function test_dashboard_counts_use_real_data(): void
    {
        $user = User::factory()->create();
        $this->applicationFor($user);
        $this->applicationFor($user, ['company_name' => 'Second Co']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('counts.applications', 2)
                ->has('recentApplications', 2));
    }
}
