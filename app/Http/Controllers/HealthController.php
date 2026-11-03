<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Machine-readable queue health for external monitoring.
 *
 * Public by design (uptime checks cannot hold credentials) but it exposes
 * aggregate counts only: no job payloads, no user data, no error text.
 */
class HealthController extends Controller
{
    public function queue(): JsonResponse
    {
        $pending = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();
        $oldest = DB::table('jobs')->min('available_at');
        $oldestAge = $oldest === null ? 0 : max(0, time() - (int) $oldest);

        $maxPending = (int) config('ai.health.max_pending', 100);
        $maxOldest = (int) config('ai.health.max_oldest_seconds', 600);
        $healthy = $pending <= $maxPending && $oldestAge <= $maxOldest;

        return response()->json([
            'healthy' => $healthy,
            'queue' => [
                'pending' => $pending,
                'failed' => $failed,
                'oldest_pending_seconds' => $oldestAge,
            ],
        ], $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
