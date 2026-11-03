<?php

namespace App\Http\Middleware;

use App\Services\Ai\AiUsageLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks AI-triggering requests once the user's daily quota is used up.
 *
 * Runs before the controller so no job is queued and no provider call is made;
 * the user gets an explicit message instead of a silently failing job.
 */
class EnsureAiQuota
{
    public function __construct(private readonly AiUsageLogger $usage) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->usage->ensureQuota($request->user());
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== AiUsageLogger::QUOTA_EXCEEDED) {
                throw $e;
            }

            $message = 'Kuota AI harian Anda sudah habis. Coba lagi besok.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 429);
            }

            return back()->with('error', $message);
        }

        return $next($request);
    }
}
