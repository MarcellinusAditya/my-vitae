<?php

namespace App\Services\Ai;

/**
 * Validates and normalises LLM job-fit output.
 *
 * The critical guardrail lives here: an evidence reference that does not exist
 * in the profile snapshot is dropped, and a requirement left without any valid
 * evidence is demoted to "not_evidenced". The model therefore cannot claim a
 * match it cannot prove.
 */
class JobFitAnalysisValidator
{
    private const MAX_REQUIREMENTS = 20;

    private const MAX_KEYWORDS = 25;

    private const MAX_REFS = 5;

    public const CATEGORIES = [
        'responsibility',
        'required_qualification',
        'preferred_qualification',
        'skill',
        'tool',
        'seniority',
    ];

    public const PRIORITIES = ['must_have', 'nice_to_have'];

    /**
     * Parse raw LLM output and normalise it.
     *
     * @param  array<string, mixed>  $profileSnapshot
     * @return array{requirements: list<array<string, mixed>>, keyword_suggestions: list<array<string, mixed>>, seniority: ?string, summary: string, demoted: int}
     *
     * @throws \RuntimeException
     */
    public static function validate(string $rawJson, array $profileSnapshot): array
    {
        $decoded = json_decode($rawJson, true);

        if (! is_array($decoded)) {
            if (preg_match('/\{.*\}/s', $rawJson, $m)) {
                $decoded = json_decode($m[0], true);
            }
        }

        if (! is_array($decoded)) {
            throw new \RuntimeException('invalid_output');
        }

        return self::normalize($decoded, $profileSnapshot);
    }

    /**
     * Apply the guardrails to an already-decoded payload.
     *
     * Called both by the parser above and by the analysis job, so a provider
     * that skips this class still cannot store unverifiable claims.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $profileSnapshot
     * @return array{requirements: list<array<string, mixed>>, keyword_suggestions: list<array<string, mixed>>, seniority: ?string, summary: string, demoted: int}
     */
    public static function normalize(array $payload, array $profileSnapshot): array
    {
        $decoded = $payload;

        $requirements = is_array($decoded['requirements'] ?? null) ? $decoded['requirements'] : [];

        if ($requirements === []) {
            throw new \RuntimeException('invalid_output');
        }

        $known = self::knownReferences($profileSnapshot);
        $demoted = 0;
        $normalised = [];

        foreach (array_slice(array_values($requirements), 0, self::MAX_REQUIREMENTS) as $requirement) {
            if (! is_array($requirement)) {
                continue;
            }

            $text = self::short($requirement['text'] ?? null, 500);

            if ($text === null) {
                continue;
            }

            $category = in_array($requirement['category'] ?? null, self::CATEGORIES, true)
                ? $requirement['category']
                : 'required_qualification';

            $priority = in_array($requirement['priority'] ?? null, self::PRIORITIES, true)
                ? $requirement['priority']
                : 'must_have';

            $refs = self::validReferences($requirement['evidence_refs'] ?? null, $known);

            $status = in_array($requirement['match_status'] ?? null, [
                'matched', 'partial', 'not_evidenced',
            ], true) ? $requirement['match_status'] : 'not_evidenced';

            // Guardrail: a claim without surviving evidence cannot stand.
            if ($refs === [] && $status !== 'not_evidenced') {
                $status = 'not_evidenced';
                $demoted++;
            }

            $normalised[] = [
                'text' => $text,
                'category' => $category,
                'priority' => $priority,
                'match_status' => $status,
                'evidence_refs' => $refs,
                'recommendation' => $status === 'matched' ? null : self::short($requirement['recommendation'] ?? null, 500),
            ];
        }

        if ($normalised === []) {
            throw new \RuntimeException('invalid_output');
        }

        // must_have first, keeping the model's relative order inside each group.
        usort($normalised, fn (array $a, array $b) => ($a['priority'] === 'must_have' ? 0 : 1) <=> ($b['priority'] === 'must_have' ? 0 : 1));

        return [
            'requirements' => $normalised,
            'keyword_suggestions' => self::keywords($decoded['keyword_suggestions'] ?? null, $known),
            'seniority' => self::short($decoded['seniority'] ?? null, 120),
            'summary' => (string) (self::short($decoded['summary'] ?? null, 1000) ?? ''),
            'demoted' => $demoted,
        ];
    }

    /**
     * Every reference the snapshot can legitimately back.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, true>
     */
    public static function knownReferences(array $snapshot): array
    {
        $map = [];

        foreach ([
            'experiences' => 'experience',
            'educations' => 'education',
            'skills' => 'skill',
            'projects' => 'project',
            'certifications' => 'certification',
        ] as $key => $type) {
            foreach ($snapshot[$key] ?? [] as $row) {
                if (isset($row['id'])) {
                    $map[$type.':'.(int) $row['id']] = true;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<string, true>  $known
     * @return list<string>
     */
    private static function validReferences(mixed $refs, array $known): array
    {
        if (! is_array($refs)) {
            return [];
        }

        $valid = [];

        foreach (array_slice(array_values($refs), 0, self::MAX_REFS) as $ref) {
            if (! is_string($ref)) {
                continue;
            }

            $normalised = strtolower(trim($ref));

            // Accept "[skill:4]" as well as "skill:4".
            $normalised = trim($normalised, '[] ');

            if (isset($known[$normalised]) && ! in_array($normalised, $valid, true)) {
                $valid[] = $normalised;
            }
        }

        return $valid;
    }

    /**
     * @param  array<string, true>  $known
     * @return list<array<string, mixed>>
     */
    private static function keywords(mixed $raw, array $known): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $keywords = [];

        foreach (array_slice(array_values($raw), 0, self::MAX_KEYWORDS) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $keyword = self::short($entry['keyword'] ?? null, 120);

            if ($keyword === null) {
                continue;
            }

            $refs = self::validReferences($entry['evidence_refs'] ?? null, $known);

            $keywords[] = [
                'keyword' => $keyword,
                // Supported only when real evidence backs it, regardless of what the model claims.
                'supported_by_profile' => $refs !== [],
                'evidence_refs' => $refs,
            ];
        }

        return $keywords;
    }

    private static function short(mixed $value, int $max): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
