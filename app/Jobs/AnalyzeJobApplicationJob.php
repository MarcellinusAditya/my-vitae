<?php

namespace App\Jobs;

use App\Models\JobRequirementAnalysis;
use App\Services\Ai\JobFitAnalysisProvider;
use App\Services\Ai\JobFitAnalysisValidator;
use App\Services\ProfileSnapshotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Maps a job posting's requirements against the candidate's profile.
 *
 * The profile and job input are snapshotted onto the analysis row so the
 * result remains reproducible after either side is edited.
 */
class AnalyzeJobApplicationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public int $analysisId) {}

    public function handle(JobFitAnalysisProvider $provider, ProfileSnapshotService $snapshots): void
    {
        $analysis = JobRequirementAnalysis::find($this->analysisId);

        if ($analysis === null || $analysis->isFinished()) {
            return;
        }

        $analysis->update([
            'status' => JobRequirementAnalysis::STATUS_PROCESSING,
            'error_code' => null,
        ]);

        $application = $analysis->jobApplication()->with('careerProfile')->first();

        if ($application === null || $application->careerProfile === null) {
            $analysis->markFailed('empty_profile');

            return;
        }

        $profileSnapshot = $analysis->profile_snapshot ?? $snapshots->build($application->careerProfile);

        if ($snapshots->isEmpty($profileSnapshot)) {
            $analysis->markFailed('empty_profile');

            return;
        }

        try {
            $result = $provider->analyze($profileSnapshot, $analysis->job_snapshot ?? []);

            // Defense in depth: re-apply the evidence guardrails whatever the provider returned.
            $result = JobFitAnalysisValidator::normalize($result, $profileSnapshot);

            $analysis->update([
                'status' => JobRequirementAnalysis::STATUS_COMPLETED,
                'error_code' => null,
                'analysis_json' => $result,
                'profile_snapshot' => $profileSnapshot,
                'model_name' => $provider->name().':'.config('services.ai.model', ''),
            ]);
        } catch (\RuntimeException $e) {
            $analysis->markFailed($this->errorCode($e));
        } catch (\Throwable $e) {
            Log::warning('Job-fit analysis failed', [
                'analysis_id' => $analysis->id,
                'error' => $e->getMessage(),
            ]);

            $analysis->markFailed('analysis_failed');
        }
    }

    private function errorCode(\RuntimeException $e): string
    {
        return array_key_exists($e->getMessage(), JobRequirementAnalysis::ERROR_MESSAGES)
            ? $e->getMessage()
            : 'analysis_failed';
    }
}
