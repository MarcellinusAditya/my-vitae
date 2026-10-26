<?php

namespace App\Services;

use App\Models\CareerProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies reviewed draft rows onto the user's career profile.
 *
 * Contract: only sections the user explicitly confirmed are applied, and even
 * then only rows the user kept. The UI submits exact field values it rendered,
 * so this service validates one final time instead of trusting the draft blob.
 */
class CvProfileMergeService
{
    private const MAX_ROWS = 50;

    /**
     * Merge `sections` from the request into the profile.
     *
     * @param  array<string, mixed>  $sections
     * @return array<string, int> number of rows written per section
     */
    public function apply(CareerProfile $profile, array $sections): array
    {
        $applied = [];

        DB::transaction(function () use ($profile, $sections, &$applied): void {
            // Contact -----------------------------------------------------------------
            if (array_key_exists('contact', $sections)) {
                $contact = $this->cleanArray($sections['contact'], [
                    'full_name', 'email', 'phone', 'location', 'linkedin_url', 'portfolio_url', 'github_url',
                ]);

                if ($contact !== []) {
                    foreach (['linkedin_url', 'portfolio_url', 'github_url'] as $urlField) {
                        if (($contact[$urlField] ?? null) !== null && filter_var($contact[$urlField], FILTER_VALIDATE_URL) === false) {
                            $contact[$urlField] = null;
                        }
                    }

                    if (($contact['email'] ?? null) !== null && filter_var($contact['email'], FILTER_VALIDATE_EMAIL) === false) {
                        $contact['email'] = null;
                    }

                    $profile->contact()->updateOrCreate([], $contact);
                    $applied['contact'] = 1;
                }
            }

            // Summary -----------------------------------------------------------------
            if (array_key_exists('summary', $sections)) {
                $text = is_array($sections['summary']) ? ($sections['summary']['text'] ?? null) : $sections['summary'];
                $text = is_string($text) ? trim(mb_substr($text, 0, 4000)) : null;

                if ($text !== null && $text !== '') {
                    $profile->update(['summary' => $text]);
                    $applied['summary'] = 1;
                }
            }

            // Row sections -------------------------------------------------------------
            $applied += $this->applyRows($profile, $sections);
        });

        return $applied;
    }

    /**
     * @param  array<string, mixed>  $sections
     * @return array<string, int>
     */
    private function applyRows(CareerProfile $profile, array $sections): array
    {
        $applied = [];

        $applied += $this->storeRows($profile, 'experiences', $sections['experiences'] ?? [], fn (array $row) => $this->experienceRow($row));
        $applied += $this->storeRows($profile, 'educations', $sections['educations'] ?? [], fn (array $row) => $this->educationRow($row));
        $applied += $this->storeRows($profile, 'skills', $sections['skills'] ?? [], fn (array $row) => $this->skillRow($row));
        $applied += $this->storeRows($profile, 'projects', $sections['projects'] ?? [], fn (array $row) => $this->projectRow($row));
        $applied += $this->storeRows($profile, 'certifications', $sections['certifications'] ?? [], fn (array $row) => $this->certificationRow($row));

        return $applied;
    }

    /**
     * @param  callable(array<string, mixed>): ?array<string, mixed>  $mapper
     * @return array<string, int>
     */
    private function storeRows(CareerProfile $profile, string $relation, mixed $rows, callable $mapper): array
    {
        if (! is_array($rows) || $rows === []) {
            return [];
        }

        $prepared = [];

        foreach (array_slice(array_values($rows), 0, self::MAX_ROWS) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $mapped = $mapper($row);

            if ($mapped !== null) {
                $prepared[] = $mapped;
            }
        }

        if ($prepared === []) {
            return [];
        }

        $nextOrder = (int) $profile->{$relation}()->max('sort_order') + 1;

        foreach ($prepared as $index => $attributes) {
            $profile->{$relation}()->create([
                ...$attributes,
                'sort_order' => $nextOrder + $index,
            ]);
        }

        return [$relation => count($prepared)];
    }

    /** @param array<string, mixed> $row */
    private function experienceRow(array $row): ?array
    {
        $organization = $this->short($row['organization'] ?? null);
        $jobTitle = $this->short($row['job_title'] ?? null);

        if ($organization === null || $jobTitle === null) {
            return null;
        }

        $start = $this->date($row['start_date'] ?? null);
        $end = $this->date($row['end_date'] ?? null);
        $isCurrent = (bool) ($row['is_current'] ?? false);

        if ($start !== null && $end !== null && $end < $start) {
            throw ValidationException::withMessages([
                'sections' => "Tanggal selesai pengalaman di {$organization} lebih awal dari tanggal mulai.",
            ]);
        }

        return [
            'organization' => $organization,
            'job_title' => $jobTitle,
            'employment_type' => $this->short($row['employment_type'] ?? null),
            'location' => $this->short($row['location'] ?? null),
            'start_date' => $start,
            'end_date' => $isCurrent ? null : $end,
            'is_current' => $isCurrent,
            'description' => $this->text($row['description'] ?? null),
            'achievements' => $this->stringList($row['achievements'] ?? null),
        ];
    }

    /** @param array<string, mixed> $row */
    private function educationRow(array $row): ?array
    {
        $institution = $this->short($row['institution'] ?? null);

        if ($institution === null) {
            return null;
        }

        return [
            'institution' => $institution,
            'degree' => $this->short($row['degree'] ?? null),
            'field_of_study' => $this->short($row['field_of_study'] ?? null),
            'start_date' => $this->date($row['start_date'] ?? null),
            'end_date' => $this->date($row['end_date'] ?? null),
            'description' => $this->text($row['description'] ?? null),
        ];
    }

    /** @param array<string, mixed> $row */
    private function skillRow(array $row): ?array
    {
        $name = $this->short($row['name'] ?? null);

        if ($name === null) {
            return null;
        }

        return [
            'name' => $name,
            'category' => $this->short($row['category'] ?? null),
            'proficiency' => $this->short($row['proficiency'] ?? null),
        ];
    }

    /** @param array<string, mixed> $row */
    private function projectRow(array $row): ?array
    {
        $name = $this->short($row['name'] ?? null);

        if ($name === null) {
            return null;
        }

        return [
            'name' => $name,
            'role' => $this->short($row['role'] ?? null),
            'description' => $this->text($row['description'] ?? null),
            'technologies' => $this->stringList($row['technologies'] ?? null),
            'url' => $this->url($row['url'] ?? null),
            'start_date' => $this->date($row['start_date'] ?? null),
            'end_date' => $this->date($row['end_date'] ?? null),
        ];
    }

    /** @param array<string, mixed> $row */
    private function certificationRow(array $row): ?array
    {
        $name = $this->short($row['name'] ?? null);

        if ($name === null) {
            return null;
        }

        return [
            'name' => $name,
            'issuer' => $this->short($row['issuer'] ?? null),
            'issue_date' => $this->date($row['issue_date'] ?? null),
            'expiry_date' => $this->date($row['expiry_date'] ?? null),
            'credential_url' => $this->url($row['credential_url'] ?? null),
        ];
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, ?string>
     */
    private function cleanArray(mixed $value, array $keys): array
    {
        if (! is_array($value)) {
            return [];
        }

        $clean = [];

        foreach ($keys as $key) {
            $clean[$key] = $this->short($value[$key] ?? null);
        }

        return array_filter($clean, fn ($v) => $v !== null) === [] ? [] : $clean;
    }

    private function short(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 255);
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, 4000);
    }

    private function url(mixed $value): ?string
    {
        $value = $this->short($value);

        if ($value === null || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $value;
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $list = [];

        foreach (array_slice(array_values($value), 0, 20) as $entry) {
            $short = $this->short($entry);

            if ($short !== null) {
                $list[] = $short;
            }
        }

        return $list;
    }

    private function date(mixed $value): ?string
    {
        $value = $this->short($value);

        if ($value === null) {
            return null;
        }

        if (preg_match('/^\d{4}$/', $value)) {
            return $value.'-01-01';
        }

        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            return $value.'-01';
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'sections' => "Format tanggal \"{$value}\" tidak dikenali.",
            ]);
        }
    }
}
