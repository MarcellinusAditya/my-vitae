<?php

namespace App\Services\Ai;

use Illuminate\Support\Carbon;

/**
 * Validates and normalises raw LLM output into the CV draft schema.
 *
 * The model is treated as untrusted: anything that does not fit the schema is
 * dropped rather than stored, dates that cannot be parsed are nulled, and
 * text fields are hard-capped so a malicious or broken CV cannot bloat the DB.
 *
 * Draft shape:
 * [
 *   'sections' => [
 *     'contact'        => ['data' => [...], 'confidence' => int],
 *     'summary'        => ['data' => ['text' => ?string], 'confidence' => int],
 *     'experiences'    => ['items' => [...], 'confidence' => int],
 *     'educations'     => ['items' => [...], 'confidence' => int],
 *     'skills'         => ['items' => [...], 'confidence' => int],
 *     'projects'       => ['items' => [...], 'confidence' => int],
 *     'certifications' => ['items' => [...], 'confidence' => int],
 *   ],
 * ]
 */
class CvDraftValidator
{
    private const MAX_ITEMS = 30;

    private const MAX_SHORT = 255;

    private const MAX_TEXT = 4000;

    private const MAX_LIST_ITEMS = 20;

    /**
     * @return array{sections: array<string, array<string, mixed>>}
     *
     * @throws \RuntimeException when the payload is unparseable or entirely empty
     */
    public static function validate(string $rawJson): array
    {
        $decoded = json_decode($rawJson, true);

        if (! is_array($decoded)) {
            // Try to salvage a JSON object buried in prose.
            if (preg_match('/\{.*\}/s', $rawJson, $m)) {
                $decoded = json_decode($m[0], true);
            }
        }

        if (! is_array($decoded)) {
            throw new \RuntimeException('invalid_output');
        }

        $confidence = is_array($decoded['confidence'] ?? null) ? $decoded['confidence'] : [];

        $sections = [
            'contact' => self::contactSection($decoded['contact'] ?? null, $confidence),
            'summary' => self::summarySection($decoded['summary'] ?? null, $confidence),
            'experiences' => self::listSection('experiences', self::mapItems($decoded['experiences'] ?? null, [self::class, 'experienceItem']), $confidence),
            'educations' => self::listSection('educations', self::mapItems($decoded['educations'] ?? null, [self::class, 'educationItem']), $confidence),
            'skills' => self::listSection('skills', self::mapItems($decoded['skills'] ?? null, [self::class, 'skillItem']), $confidence),
            'projects' => self::listSection('projects', self::mapItems($decoded['projects'] ?? null, [self::class, 'projectItem']), $confidence),
            'certifications' => self::listSection('certifications', self::mapItems($decoded['certifications'] ?? null, [self::class, 'certificationItem']), $confidence),
        ];

        // Nothing usable at all → treat the output as invalid so the job can fail cleanly.
        $hasAnything = self::contactHasData($sections['contact']['data'])
            || $sections['summary']['data']['text'] !== null
            || collect($sections)->except(['contact', 'summary'])->contains(fn (array $s) => $s['items'] !== []);

        if (! $hasAnything) {
            throw new \RuntimeException('invalid_output');
        }

        return ['sections' => $sections];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function mapItems(mixed $raw, callable $mapper): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $items = [];

        foreach (array_slice(array_values($raw), 0, self::MAX_ITEMS) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $mapped = $mapper($item);

            if ($mapped !== null) {
                $items[] = $mapped;
            }
        }

        return $items;
    }

    /** @param array<string, mixed> $confidence */
    private static function contactSection(mixed $raw, array $confidence): array
    {
        $data = [
            'full_name' => self::short($raw['full_name'] ?? null),
            'email' => self::short($raw['email'] ?? null),
            'phone' => self::short($raw['phone'] ?? null),
            'location' => self::short($raw['location'] ?? null),
            'linkedin_url' => self::url($raw['linkedin_url'] ?? null),
            'portfolio_url' => self::url($raw['portfolio_url'] ?? null),
            'github_url' => self::url($raw['github_url'] ?? null),
        ];

        return ['data' => $data, 'confidence' => self::confidence($raw['confidence'] ?? ($confidence['contact'] ?? null))];
    }

    /** @param array<string, mixed> $confidence */
    private static function summarySection(mixed $raw, array $confidence): array
    {
        $text = is_array($raw) ? self::text($raw['text'] ?? null) : self::text($raw);

        return ['data' => ['text' => $text], 'confidence' => self::confidence($raw['confidence'] ?? ($confidence['summary'] ?? null))];
    }

    /** @param list<array<string, mixed>> $items @param array<string, mixed> $confidence */
    private static function listSection(string $key, array $items, array $confidence): array
    {
        return ['items' => $items, 'confidence' => self::confidence($confidence[$key] ?? null)];
    }

    /** @param array<string, mixed> $item */
    private static function experienceItem(array $item): ?array
    {
        $organization = self::short($item['organization'] ?? null);
        $jobTitle = self::short($item['job_title'] ?? null);

        if ($organization === null && $jobTitle === null) {
            return null;
        }

        [$start, $end] = self::dates($item['start_date'] ?? null, $item['end_date'] ?? null);

        return [
            'organization' => $organization ?? '',
            'job_title' => $jobTitle ?? '',
            'employment_type' => self::short($item['employment_type'] ?? null),
            'location' => self::short($item['location'] ?? null),
            'start_date' => $start,
            'end_date' => $end,
            'is_current' => (bool) ($item['is_current'] ?? ($end === null && $start !== null)),
            'description' => self::text($item['description'] ?? null),
            'achievements' => self::stringList($item['achievements'] ?? null),
        ];
    }

    /** @param array<string, mixed> $item */
    private static function educationItem(array $item): ?array
    {
        $institution = self::short($item['institution'] ?? null);

        if ($institution === null) {
            return null;
        }

        [$start, $end] = self::dates($item['start_date'] ?? null, $item['end_date'] ?? null);

        return [
            'institution' => $institution,
            'degree' => self::short($item['degree'] ?? null),
            'field_of_study' => self::short($item['field_of_study'] ?? null),
            'start_date' => $start,
            'end_date' => $end,
            'description' => self::text($item['description'] ?? null),
        ];
    }

    /** @param array<string, mixed> $item */
    private static function skillItem(array $item): ?array
    {
        $name = self::short($item['name'] ?? null);

        if ($name === null) {
            return null;
        }

        return [
            'name' => $name,
            'category' => self::short($item['category'] ?? null),
            'proficiency' => self::short($item['proficiency'] ?? null),
        ];
    }

    /** @param array<string, mixed> $item */
    private static function projectItem(array $item): ?array
    {
        $name = self::short($item['name'] ?? null);

        if ($name === null) {
            return null;
        }

        [$start, $end] = self::dates($item['start_date'] ?? null, $item['end_date'] ?? null);

        return [
            'name' => $name,
            'role' => self::short($item['role'] ?? null),
            'description' => self::text($item['description'] ?? null),
            'technologies' => self::stringList($item['technologies'] ?? null),
            'url' => self::url($item['url'] ?? null),
            'start_date' => $start,
            'end_date' => $end,
        ];
    }

    /** @param array<string, mixed> $item */
    private static function certificationItem(array $item): ?array
    {
        $name = self::short($item['name'] ?? null);

        if ($name === null) {
            return null;
        }

        [$start, $end] = self::dates($item['issue_date'] ?? null, $item['expiry_date'] ?? null);

        return [
            'name' => $name,
            'issuer' => self::short($item['issuer'] ?? null),
            'issue_date' => $start,
            'expiry_date' => $end,
            'credential_url' => self::url($item['credential_url'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function contactHasData(array $data): bool
    {
        return collect($data)->except(['confidence'])->contains(fn ($value) => $value !== null);
    }

    private static function short(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, self::MAX_SHORT);
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, self::MAX_TEXT);
    }

    private static function url(mixed $value): ?string
    {
        $value = self::short($value);

        if ($value === null) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $value;
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $list = [];

        foreach (array_slice(array_values($value), 0, self::MAX_LIST_ITEMS) as $entry) {
            $short = self::short($entry);

            if ($short !== null) {
                $list[] = $short;
            }
        }

        return $list;
    }

    /**
     * Normalise a date-ish string ("2023", "2023-01", "Jan 2023", "2023-01-15")
     * into Y-m-d when possible; keep the raw string when it is only a year or
     * year-month so nothing is silently misdated.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private static function dates(mixed $start, mixed $end): array
    {
        return [self::date($start), self::date($end)];
    }

    private static function date(mixed $value): ?string
    {
        $value = self::short($value);

        if ($value === null) {
            return null;
        }

        // Year-only ("2023") and year-month ("2023-01") get a placeholder day so
        // the value is storable; the review UI lets the user fix the exact date.
        if (preg_match('/^\d{4}$/', $value)) {
            return $value.'-01-01';
        }

        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            return $value.'-01';
        }

        $hasDigit = preg_match('/\d/', $value) === 1;

        if (! $hasDigit) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function confidence(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 50;
        }

        return max(0, min(100, (int) $value));
    }
}
