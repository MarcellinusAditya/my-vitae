<?php

namespace App\Services\Ai;

use App\Models\AiUsageLog;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Records AI operations without ever storing prompts or CV text, and enforces
 * the per-user daily quota.
 */
class AiUsageLogger
{
    /** Error code thrown when the daily quota is exhausted. */
    public const QUOTA_EXCEEDED = 'quota_exceeded';

    /**
     * Throws when the user already used the daily allowance.
     *
     * @throws \RuntimeException with the code `quota_exceeded`
     */
    public function ensureQuota(?User $user): void
    {
        if ($user === null) {
            return;
        }

        $limit = (int) config('ai.limits.daily_quota', 50);

        if ($limit <= 0) {
            return;
        }

        $used = AiUsageLog::query()
            ->where('user_id', $user->id)
            ->where('status', AiUsageLog::STATUS_SUCCESS)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($used >= $limit) {
            throw new \RuntimeException(self::QUOTA_EXCEEDED);
        }
    }

    /**
     * Successful operations left for this user today (null = unlimited).
     */
    public function remainingQuota(?User $user): ?int
    {
        if ($user === null) {
            return null;
        }

        $limit = (int) config('ai.limits.daily_quota', 50);

        if ($limit <= 0) {
            return null;
        }

        $used = AiUsageLog::query()
            ->where('user_id', $user->id)
            ->where('status', AiUsageLog::STATUS_SUCCESS)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        return max(0, $limit - $used);
    }

    public function success(
        string $operation,
        string $provider,
        ?string $model,
        int $durationMs,
        ?User $user = null,
        ?JobApplication $application = null,
        ?int $inputTokens = null,
        ?int $outputTokens = null,
    ): AiUsageLog {
        return $this->write([
            'status' => AiUsageLog::STATUS_SUCCESS,
            'operation' => $operation,
            'provider' => $provider,
            'model' => $model,
            'duration_ms' => max(0, $durationMs),
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'user_id' => $user?->id,
            'job_application_id' => $application?->id,
            'error_code' => null,
        ]);
    }

    public function failed(
        string $operation,
        string $provider,
        ?string $model,
        int $durationMs,
        string $errorCode,
        ?User $user = null,
        ?JobApplication $application = null,
    ): AiUsageLog {
        return $this->write([
            'status' => AiUsageLog::STATUS_FAILED,
            'operation' => $operation,
            'provider' => $provider,
            'model' => $model,
            'duration_ms' => max(0, $durationMs),
            'input_tokens' => null,
            'output_tokens' => null,
            'user_id' => $user?->id,
            'job_application_id' => $application?->id,
            'error_code' => $errorCode,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function write(array $attributes): AiUsageLog
    {
        try {
            return AiUsageLog::create($attributes);
        } catch (\Throwable $e) {
            // Never let observability break the product path. The message is
            // generic on purpose: no prompt or CV text may reach the log.
            Log::warning('Could not write AI usage log', ['error' => $e::class]);

            return new AiUsageLog($attributes);
        }
    }
}
