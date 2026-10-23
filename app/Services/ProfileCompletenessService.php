<?php

namespace App\Services;

use App\Models\CareerProfile;

class ProfileCompletenessService
{
    /** Sections counted toward completeness, in display order. */
    public const SECTIONS = [
        'contact' => 'Contact details',
        'summary' => 'Professional summary',
        'experiences' => 'Work experience',
        'educations' => 'Education',
        'skills' => 'Skills',
        'projects' => 'Projects',
        'certifications' => 'Certifications',
    ];

    /**
     * Report which sections have data, the percentage filled, and the next gap.
     *
     * @return array{filled: int, total: int, percentage: int, completed: list<string>, missing: list<string>, next_section: string|null}
     */
    public function forProfile(CareerProfile $profile): array
    {
        $profile->loadMissing(['contact', 'experiences', 'educations', 'skills', 'projects', 'certifications']);

        $has = [
            'contact' => $profile->contact !== null
                && filled($profile->contact->full_name)
                && (filled($profile->contact->email) || filled($profile->contact->phone)),
            'summary' => filled($profile->summary),
            'experiences' => $profile->experiences->isNotEmpty(),
            'educations' => $profile->educations->isNotEmpty(),
            'skills' => $profile->skills->isNotEmpty(),
            'projects' => $profile->projects->isNotEmpty(),
            'certifications' => $profile->certifications->isNotEmpty(),
        ];

        $completed = [];
        $missing = [];

        foreach (array_keys(self::SECTIONS) as $key) {
            if ($has[$key]) {
                $completed[] = $key;
            } else {
                $missing[] = $key;
            }
        }

        $total = count(self::SECTIONS);
        $filled = count($completed);

        return [
            'filled' => $filled,
            'total' => $total,
            'percentage' => (int) round(($filled / $total) * 100),
            'completed' => $completed,
            'missing' => $missing,
            'next_section' => $missing[0] ?? null,
        ];
    }
}
