<?php

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Models\GeneratedDocument;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\Ai\AiUsageLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Each test gets a clean limiter store.
        RateLimiter::clear('user:');
    }

    private function applicationFor(User $user): JobApplication
    {
        $profile = $user->careerProfile()->create(['summary' => 'Engineer.']);

        return JobApplication::create([
            'user_id' => $user->id,
            'career_profile_id' => $profile->id,
            'company_name' => 'Acme',
            'job_title' => 'Dev',
            'job_description' => str_repeat('text ', 30),
            'language' => 'id',
        ]);
    }

    public function test_ai_endpoint_is_throttled_per_hour(): void
    {
        config(['ai.limits.per_hour' => 2, 'ai.limits.daily_quota' => 0]);

        $user = User::factory()->create();
        $application = $this->applicationFor($user);

        // First two requests pass the limiter (quota disabled so it cannot
        // interfere), the third is rejected with 429.
        $this->actingAs($user)->post(route('applications.analyze', $application));
        $this->actingAs($user)->post(route('applications.analyze', $application));

        $response = $this->actingAs($user)->post(route('applications.analyze', $application));

        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
    }

    public function test_export_endpoint_is_throttled(): void
    {
        config(['ai.limits.exports_per_hour' => 1]);

        $user = User::factory()->create();
        $application = $this->applicationFor($user);
        $document = $application->generatedDocuments()->create([
            'type' => GeneratedDocument::TYPE_RESUME,
            'title' => 'CV',
            'version' => 1,
            'language' => 'id',
            'generation_status' => GeneratedDocument::STATUS_COMPLETED,
            'content_json' => ['summary' => 'S', 'skills' => [], 'experiences' => []],
        ]);

        $this->actingAs($user)->post(route('documents.exports.store', $document), ['format' => 'pdf'])
            ->assertRedirect();

        $this->actingAs($user)->post(route('documents.exports.store', $document), ['format' => 'pdf'])
            ->assertStatus(429);
    }

    public function test_limits_are_per_user_not_global(): void
    {
        config(['ai.limits.per_hour' => 2]);

        $first = User::factory()->create();
        $second = User::factory()->create();

        RateLimiter::hit('aiuser:'.$first->id, 3600);
        RateLimiter::hit('aiuser:'.$first->id, 3600);

        $this->assertTrue(RateLimiter::tooManyAttempts('aiuser:'.$first->id, 2));
        $this->assertFalse(RateLimiter::tooManyAttempts('aiuser:'.$second->id, 2));
    }

    public function test_quota_is_enforced_before_the_job_is_queued(): void
    {
        config(['ai.limits.daily_quota' => 2, 'queue.default' => 'database']);

        $user = User::factory()->create();
        $application = $this->applicationFor($user);

        AiUsageLog::create([
            'user_id' => $user->id,
            'status' => AiUsageLog::STATUS_SUCCESS,
            'operation' => 'job_fit',
            'provider' => 'test',
            'model' => 'test',
            'duration_ms' => 10,
        ]);
        AiUsageLog::create([
            'user_id' => $user->id,
            'status' => AiUsageLog::STATUS_SUCCESS,
            'operation' => 'resume',
            'provider' => 'test',
            'model' => 'test',
            'duration_ms' => 10,
        ]);

        $this->assertDatabaseCount('jobs', 0);

        $response = $this->actingAs($user)->post(route('applications.analyze', $application));

        // Browser/Inertia path: flash an error instead of a bare 429 page.
        $response->assertRedirect();
        $response->assertSessionHas('error');
        // No job was dispatched, so no AI call can happen.
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_quota_returns_429_for_json_callers(): void
    {
        config(['ai.limits.daily_quota' => 1]);

        $user = User::factory()->create();
        $application = $this->applicationFor($user);

        AiUsageLog::create([
            'user_id' => $user->id,
            'status' => AiUsageLog::STATUS_SUCCESS,
            'operation' => 'job_fit',
            'provider' => 'test',
            'model' => 'test',
            'duration_ms' => 10,
        ]);

        $this->actingAs($user)
            ->postJson(route('applications.analyze', $application))
            ->assertStatus(429)
            ->assertJsonPath('message', 'Kuota AI harian Anda sudah habis. Coba lagi besok.');
    }

    public function test_failed_runs_do_not_consume_quota(): void
    {
        $user = User::factory()->create();

        AiUsageLog::create([
            'user_id' => $user->id,
            'status' => AiUsageLog::STATUS_FAILED,
            'operation' => 'job_fit',
            'provider' => 'test',
            'model' => 'test',
            'duration_ms' => 10,
            'error_code' => 'invalid_output',
        ]);

        $remaining = app(AiUsageLogger::class)->remainingQuota($user);

        $this->assertSame((int) config('ai.limits.daily_quota'), $remaining);
    }
}
