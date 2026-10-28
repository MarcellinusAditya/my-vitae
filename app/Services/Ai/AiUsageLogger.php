<?php

namespace App\Services\Ai;

use App\Models\AiUsageLog;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Records AI operations without ever storing prompts or CV text.
 */
class AiUsageLogger
{
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
            // Never let observability break the product path.
            Log::warning('Could not write AI usage log', ['error' => $e->getMessage()]);

            return new AiUsageLog($attributes);
        }
    }
}
