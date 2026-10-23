<?php

namespace App\Services;

use App\Models\CareerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CareerProfileService
{
    /**
     * Return the user's single career profile, creating an empty one on first visit.
     */
    public function forUser(User $user): CareerProfile
    {
        $profile = $user->careerProfile()->first();

        if ($profile === null) {
            $profile = DB::transaction(function () use ($user) {
                return CareerProfile::create([
                    'user_id' => $user->id,
                    'default_language' => 'id',
                    'writing_style' => 'professional',
                    'resume_length' => 'concise',
                    'date_format' => 'month_year',
                ]);
            });
        }

        return $profile;
    }

    /**
     * Shape the profile for the Inertia page: every section plus completion data.
     *
     * @return array<string, mixed>
     */
    public function toPagePayload(CareerProfile $profile): array
    {
        $profile->load([
            'contact',
            'experiences',
            'educations',
            'skills',
            'projects',
            'certifications',
        ]);

        return [
            'profile' => [
                'id' => $profile->id,
                'title' => $profile->title,
                'summary' => $profile->summary,
                'default_language' => $profile->default_language,
                'writing_style' => $profile->writing_style,
                'resume_length' => $profile->resume_length,
                'date_format' => $profile->date_format,
            ],
            'contact' => $profile->contact,
            'experiences' => $profile->experiences,
            'educations' => $profile->educations,
            'skills' => $profile->skills,
            'projects' => $profile->projects,
            'certifications' => $profile->certifications,
            'options' => [
                'languages' => CareerProfile::LANGUAGES,
                'writingStyles' => CareerProfile::WRITING_STYLES,
                'resumeLengths' => CareerProfile::RESUME_LENGTHS,
                'dateFormats' => CareerProfile::DATE_FORMATS,
            ],
            'completion' => app(ProfileCompletenessService::class)->forProfile($profile),
        ];
    }
}
