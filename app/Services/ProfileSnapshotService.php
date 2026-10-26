<?php

namespace App\Services;

use App\Models\CareerProfile;

/**
 * Builds an immutable, id-tagged snapshot of the profile.
 *
 * The analysis stores this snapshot so an old result stays explainable even
 * after the user edits the profile — every evidence reference points at a row
 * that existed at analysis time.
 */
class ProfileSnapshotService
{
    /**
     * @return array<string, mixed>
     */
    public function build(CareerProfile $profile): array
    {
        $profile->loadMissing(['contact', 'experiences', 'educations', 'skills', 'projects', 'certifications']);

        return [
            'contact' => $profile->contact === null ? null : [
                'full_name' => $profile->contact->full_name,
                'email' => $profile->contact->email,
                'phone' => $profile->contact->phone,
                'location' => $profile->contact->location,
                'linkedin_url' => $profile->contact->linkedin_url,
                'portfolio_url' => $profile->contact->portfolio_url,
                'github_url' => $profile->contact->github_url,
            ],
            'summary' => $profile->summary,
            'experiences' => $profile->experiences->map(fn ($item) => [
                'id' => $item->id,
                'organization' => $item->organization,
                'job_title' => $item->job_title,
                'employment_type' => $item->employment_type,
                'location' => $item->location,
                'start_date' => $item->start_date?->toDateString(),
                'end_date' => $item->end_date?->toDateString(),
                'is_current' => (bool) $item->is_current,
                'description' => $item->description,
                'achievements' => $item->achievements ?? [],
            ])->values()->all(),
            'educations' => $profile->educations->map(fn ($item) => [
                'id' => $item->id,
                'institution' => $item->institution,
                'degree' => $item->degree,
                'field_of_study' => $item->field_of_study,
                'start_date' => $item->start_date?->toDateString(),
                'end_date' => $item->end_date?->toDateString(),
                'description' => $item->description,
            ])->values()->all(),
            'skills' => $profile->skills->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
                'proficiency' => $item->proficiency,
            ])->values()->all(),
            'projects' => $profile->projects->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'role' => $item->role,
                'description' => $item->description,
                'technologies' => $item->technologies ?? [],
                'url' => $item->url,
                'start_date' => $item->start_date?->toDateString(),
                'end_date' => $item->end_date?->toDateString(),
            ])->values()->all(),
            'certifications' => $profile->certifications->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'issuer' => $item->issuer,
                'issue_date' => $item->issue_date?->toDateString(),
                'expiry_date' => $item->expiry_date?->toDateString(),
            ])->values()->all(),
        ];
    }

    /**
     * True when the profile has nothing an analysis could cite as evidence.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function isEmpty(array $snapshot): bool
    {
        return blank($snapshot['summary'] ?? null)
            && ($snapshot['contact'] ?? null) === null
            && ($snapshot['experiences'] ?? []) === []
            && ($snapshot['educations'] ?? []) === []
            && ($snapshot['skills'] ?? []) === []
            && ($snapshot['projects'] ?? []) === []
            && ($snapshot['certifications'] ?? []) === [];
    }

    /**
     * Compact text rendering of the snapshot, used as LLM input.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function toPromptText(array $snapshot): string
    {
        $lines = [];

        if (! empty($snapshot['contact']['full_name'])) {
            $lines[] = 'Name: '.$snapshot['contact']['full_name'];
        }

        if (! empty($snapshot['contact']['location'])) {
            $lines[] = 'Location: '.$snapshot['contact']['location'];
        }

        if (! empty($snapshot['summary'])) {
            $lines[] = "\nSUMMARY:\n".$snapshot['summary'];
        }

        foreach (['experiences' => 'EXPERIENCE', 'educations' => 'EDUCATION', 'skills' => 'SKILLS', 'projects' => 'PROJECTS', 'certifications' => 'CERTIFICATIONS'] as $key => $heading) {
            $rows = $snapshot[$key] ?? [];

            if ($rows === []) {
                continue;
            }

            $lines[] = "\n{$heading}:";

            foreach ($rows as $row) {
                $lines[] = match ($key) {
                    'experiences' => sprintf(
                        '- [experience:%d] %s at %s (%s - %s). %s %s',
                        $row['id'],
                        $row['job_title'],
                        $row['organization'],
                        $row['start_date'] ?? '?',
                        $row['is_current'] ? 'present' : ($row['end_date'] ?? '?'),
                        (string) ($row['description'] ?? ''),
                        $row['achievements'] === [] ? '' : 'Achievements: '.implode('; ', $row['achievements']),
                    ),
                    'educations' => sprintf(
                        '- [education:%d] %s, %s %s (%s - %s)',
                        $row['id'],
                        $row['institution'],
                        (string) ($row['degree'] ?? ''),
                        (string) ($row['field_of_study'] ?? ''),
                        $row['start_date'] ?? '?',
                        $row['end_date'] ?? '?',
                    ),
                    'skills' => sprintf(
                        '- [skill:%d] %s%s',
                        $row['id'],
                        $row['name'],
                        $row['category'] ? ' ('.$row['category'].')' : '',
                    ),
                    'projects' => sprintf(
                        '- [project:%d] %s%s. %s Technologies: %s',
                        $row['id'],
                        $row['name'],
                        $row['role'] ? ' ('.$row['role'].')' : '',
                        (string) ($row['description'] ?? ''),
                        implode(', ', $row['technologies']),
                    ),
                    'certifications' => sprintf(
                        '- [certification:%d] %s%s',
                        $row['id'],
                        $row['name'],
                        $row['issuer'] ? ' by '.$row['issuer'] : '',
                    ),
                };
            }
        }

        return implode("\n", $lines);
    }
}
