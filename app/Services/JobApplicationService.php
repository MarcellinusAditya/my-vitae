<?php

namespace App\Services;

use App\Models\CareerProfile;
use App\Models\JobApplication;
use App\Models\JobRequirementAnalysis;
use Illuminate\Support\Facades\DB;

/**
 * Creates job applications and queues analyses.
 */
class JobApplicationService
{
    public function __construct(private readonly ProfileSnapshotService $snapshots) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(CareerProfile $profile, array $attributes): JobApplication
    {
        return DB::transaction(function () use ($profile, $attributes) {
            return JobApplication::create([
                ...$attributes,
                'user_id' => $profile->user_id,
                'career_profile_id' => $profile->id,
                'status' => $attributes['status'] ?? JobApplication::STATUS_DRAFT,
            ]);
        });
    }

    /**
     * Create a fresh draft copy of an application (same posting, no analyses).
     */
    public function duplicate(JobApplication $application): JobApplication
    {
        return DB::transaction(function () use ($application) {
            $copy = $application->replicate(['status']);
            $copy->status = JobApplication::STATUS_DRAFT;
            $copy->save();

            return $copy;
        });
    }

    /**
     * Queue an analysis for the application.
     *
     * The profile snapshot is taken now so the result reflects the profile at
     * request time even if the user keeps editing while the job runs.
     */
    public function queueAnalysis(JobApplication $application): JobRequirementAnalysis
    {
        $profile = $application->careerProfile;
        $profileSnapshot = $profile === null ? null : $this->snapshots->build($profile);

        $analysis = $application->analyses()->create([
            'status' => JobRequirementAnalysis::STATUS_PENDING,
            'profile_snapshot' => $profileSnapshot,
            'job_snapshot' => $this->jobSnapshot($application),
        ]);

        return $analysis;
    }

    /**
     * @return array<string, mixed>
     */
    private function jobSnapshot(JobApplication $application): array
    {
        return [
            'company_name' => $application->company_name,
            'job_title' => $application->job_title,
            'job_location' => $application->job_location,
            'job_description' => $application->job_description,
            'job_qualifications' => $application->job_qualifications,
            'language' => $application->language,
            'notes' => $application->notes,
        ];
    }
}
