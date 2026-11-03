<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_health_endpoint_is_public_and_reports_counts(): void
    {
        // No authentication: uptime checks cannot hold credentials.
        $response = $this->getJson(route('health.queue'));

        $response->assertOk()
            ->assertJsonPath('healthy', true)
            ->assertJsonPath('queue.pending', 0)
            ->assertJsonPath('queue.failed', 0)
            ->assertJsonStructure(['healthy', 'queue' => ['pending', 'failed', 'oldest_pending_seconds']]);
    }

    public function test_queue_health_returns_503_when_backlog_exceeds_threshold(): void
    {
        config(['ai.health.max_pending' => 1]);

        foreach (range(1, 3) as $i) {
            DB::table('jobs')->insert([
                'queue' => 'default',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => time(),
                'created_at' => time(),
            ]);
        }

        $this->getJson(route('health.queue'))
            ->assertStatus(503)
            ->assertJsonPath('healthy', false);
    }

    public function test_queue_health_command_exits_non_zero_when_degraded(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time(),
            'created_at' => time(),
        ]);

        $this->artisan('app:queue-health', ['--max-pending' => 0])->assertExitCode(1);
    }

    public function test_queue_health_command_exits_zero_when_healthy(): void
    {
        $this->artisan('app:queue-health')->assertExitCode(0);
    }
}
