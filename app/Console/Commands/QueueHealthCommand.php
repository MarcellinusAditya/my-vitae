<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Exit code is non-zero when the queue is unhealthy, so external monitoring
 * (cron + alerting, uptime check) can page without parsing output.
 *
 * Output carries aggregate counts only: never job payloads or user data.
 */
class QueueHealthCommand extends Command
{
    protected $signature = 'app:queue-health {--max-pending=100 : Pending jobs allowed} {--max-oldest=600 : Max age in seconds of the oldest pending job} {--json}';

    protected $description = 'Report queue health; exits non-zero when the queue is degraded';

    public function handle(): int
    {
        $pending = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();

        $oldest = DB::table('jobs')->min('available_at');
        $oldestAge = $oldest === null ? 0 : max(0, time() - (int) $oldest);

        $maxPending = (int) $this->option('max-pending');
        $maxOldest = (int) $this->option('max-oldest');

        $healthy = $pending <= $maxPending && $oldestAge <= $maxOldest;

        $payload = [
            'healthy' => $healthy,
            'pending' => $pending,
            'failed' => $failed,
            'oldest_pending_seconds' => $oldestAge,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload));

            return $healthy ? self::SUCCESS : self::FAILURE;
        }

        $this->line('pending: '.$pending.' (max '.$maxPending.')');
        $this->line('failed: '.$failed);
        $this->line('oldest pending: '.$oldestAge.'s (max '.$maxOldest.'s)');

        if (! $healthy) {
            $this->error('QUEUE DEGRADED');

            return self::FAILURE;
        }

        $this->info('QUEUE HEALTHY');

        return self::SUCCESS;
    }
}
