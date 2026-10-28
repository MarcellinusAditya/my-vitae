<?php

namespace App\Services\Ai;

/**
 * Enforces the product's AI guardrails on generated documents.
 *
 * Three checks, in order of severity:
 *  1. Hard drop - a row whose required facts (organization, role, dates,
 *     institution, ...) do not match the profile snapshot is discarded.
 *  2. Demotion - a row that loses every evidence ref is kept but marked
 *     unverified so the user sees it instead of silently trusting it.
 *  3. Flagging - a bullet containing a number that does not appear anywhere
 *     in the source snapshot is flagged as an unverified metric.
 */
class DocumentFactValidator
{
    private const MAX_BULLETS = 8;

    /** Numbers with at least 2 digits, optionally with separators/decimal/percent/plus. */
    private const METRIC_PATTERN = '/(?<![\d.])(\d[\d.,]*\s*(?:%|persen|percent|x|k|jt|juta|miliar|billion|million)?)(?![\d])/i';

    /**
     * @param  array<string, mixed>  $profileSnapshot
     * @return array<string, mixed>
     */
    public static function validateResume(string $rawJson, array $profileSnapshot): array
    {
        $decoded = self::decode($rawJson);
        $known = self::knownReferences($profileSnapshot);
        $sourceText = self::sourceText($profileSnapshot);

        $unverified = [];
        $dropped = 0;

        $experiences = [];

        foreach (self::list($decoded['experiences'] ?? null) as $row) {
            $organization = self::text($row['organization'] ?? null, 255);
            $role = self::text($row['role'] ?? null, 255);

            if ($organization === null || $role === null) {
                $dropped++;

                continue;
            }

            $source = self::findSource($profileSnapshot['experiences'] ?? [], $organization, $role);

            if ($source === null) {
                // The model invented an employer or role: never store it.
                $dropped++;

                continue;
            }

            $refs = self::refs($row['evidence_refs'] ?? null, $known);

            if ($refs === []) {
                $refs = ['experience:'.$source['id']];
            }

            [$bullets, $flags] = self::bullets($row['bullets'] ?? null, $sourceText);
            $unverified = [...$unverified, ...$flags];

            $experiences[] = [
                'organization' => $source['organization'],
                'role' => $source['job_title'],
                'start_date' => $source['start_date'] ?? null,
                'end_date' => $source['end_date'] ?? null,
                'is_current' => (bool) ($source['is_current'] ?? false),
                'bullets' => $bullets,
                'evidence_refs' => $refs,
            ];
        }

        $projects = [];

        foreach (self::list($decoded['projects'] ?? null) as $row) {
            $name = self::text($row['name'] ?? null, 255);

            if ($name === null) {
                continue;
            }

            $source = self::findByName($profileSnapshot['projects'] ?? [], $name);

            if ($source === null) {
                $dropped++;

                continue;
            }

            $refs = self::refs($row['evidence_refs'] ?? null, $known) ?: ['project:'.$source['id']];
            [$bullets, $flags] = self::bullets($row['bullets'] ?? null, $sourceText);
            $unverified = [...$unverified, ...$flags];

            $projects[] = [
                'name' => $source['name'],
                'role' => $source['role'] ?? null,
                'bullets' => $bullets,
                'technologies' => self::strings($row['technologies'] ?? null, 20),
                'evidence_refs' => $refs,
            ];
        }

        $education = [];

        foreach (self::list($decoded['education'] ?? $decoded['educations'] ?? null) as $row) {
            $institution = self::text($row['institution'] ?? null, 255);

            if ($institution === null) {
                continue;
            }

            $source = self::findByName($profileSnapshot['educations'] ?? [], $institution);

            if ($source === null) {
                $dropped++;

                continue;
            }

            $education[] = [
                'institution' => $source['institution'],
                'degree' => $source['degree'] ?? null,
                'field_of_study' => $source['field_of_study'] ?? null,
                'start_date' => $source['start_date'] ?? null,
                'end_date' => $source['end_date'] ?? null,
                'evidence_refs' => self::refs($row['evidence_refs'] ?? null, $known) ?: ['education:'.$source['id']],
            ];
        }

        $certifications = [];

        foreach (self::list($decoded['certifications'] ?? null) as $row) {
            $name = self::text($row['name'] ?? null, 255);

            if ($name === null) {
                continue;
            }

            $source = self::findByName($profileSnapshot['certifications'] ?? [], $name);

            if ($source === null) {
                $dropped++;

                continue;
            }

            $certifications[] = [
                'name' => $source['name'],
                'issuer' => $source['issuer'] ?? null,
                'issue_date' => $source['issue_date'] ?? null,
                'evidence_refs' => self::refs($row['evidence_refs'] ?? null, $known) ?: ['certification:'.$source['id']],
            ];
        }

        $skills = [];

        foreach (self::list($decoded['skills'] ?? null) as $row) {
            $name = self::text($row['name'] ?? null, 120);

            if ($name === null) {
                continue;
            }

            $refs = self::refs($row['evidence_refs'] ?? null, $known);
            $match = self::findSkill($profileSnapshot['skills'] ?? [], $name);

            if ($match !== null && $refs === []) {
                $refs = ['skill:'.$match['id']];
            }

            // A skill with no supporting row is fabricated — drop it.
            if ($refs === []) {
                $dropped++;

                continue;
            }

            $skills[] = [
                'name' => $name,
                'category' => self::text($row['category'] ?? null, 80),
                'evidence_refs' => $refs,
            ];
        }

        $summary = self::text($decoded['summary'] ?? null, 2000);

        if ($summary === null && $experiences === [] && $skills === []) {
            throw new \RuntimeException('invalid_output');
        }

        return [
            'summary' => $summary,
            'skills' => $skills,
            'experiences' => $experiences,
            'projects' => $projects,
            'education' => $education,
            'certifications' => $certifications,
            'languages' => self::strings($decoded['languages'] ?? null, 12),
            'unverified_claims' => array_values(array_unique([
                ...$unverified,
                ...self::strings($decoded['unverified_claims'] ?? null, 20),
            ])),
            'dropped' => $dropped,
            'prompt_version' => OpenAiCompatibleDocumentGeneration::PROMPT_VERSION,
        ];
    }

    /**
     * @param  array<string, mixed>  $profileSnapshot
     * @param  array<string, mixed>  $jobSnapshot
     * @return array<string, mixed>
     */
    public static function validateCoverLetter(string $rawJson, array $profileSnapshot, array $jobSnapshot): array
    {
        $decoded = self::decode($rawJson);
        $known = self::knownReferences($profileSnapshot);
        $sourceText = self::sourceText($profileSnapshot);

        $recipient = self::text($decoded['recipient_line'] ?? null, 255);
        $recipient = self::neutralRecipient($recipient, (string) ($jobSnapshot['language'] ?? 'id'));

        $paragraphs = [];
        $unverified = [];

        foreach (self::list($decoded['body_paragraphs'] ?? null) as $paragraph) {
            $text = is_array($paragraph)
                ? self::text($paragraph['text'] ?? null, 4000)
                : self::text($paragraph, 4000);

            if ($text === null) {
                continue;
            }

            $unverified = [...$unverified, ...self::metricFlags($text, $sourceText)];

            $paragraphs[] = [
                'text' => $text,
                'evidence_refs' => is_array($paragraph) ? self::refs($paragraph['evidence_refs'] ?? null, $known) : [],
            ];
        }

        $opening = self::text($decoded['opening'] ?? null, 2000);
        $closing = self::text($decoded['closing'] ?? null, 2000);

        if ($opening === null && $paragraphs === []) {
            throw new \RuntimeException('invalid_output');
        }

        if ($opening !== null) {
            $unverified = [...$unverified, ...self::metricFlags($opening, $sourceText)];
        }

        if ($closing !== null) {
            $unverified = [...$unverified, ...self::metricFlags($closing, $sourceText)];
        }

        return [
            'recipient_line' => $recipient,
            'opening' => $opening,
            'body_paragraphs' => $paragraphs,
            'closing' => $closing,
            'signature' => self::text($decoded['signature'] ?? null, 255)
                ?? (string) ($profileSnapshot['contact']['full_name'] ?? ''),
            'evidence_refs' => self::refs($decoded['evidence_refs'] ?? null, $known),
            'unverified_claims' => array_values(array_unique([
                ...$unverified,
                ...self::strings($decoded['unverified_claims'] ?? null, 20),
            ])),
            'prompt_version' => OpenAiCompatibleDocumentGeneration::PROMPT_VERSION,
        ];
    }

    /**
     * Force a neutral greeting when no real recipient name is available.
     */
    public static function neutralRecipient(?string $recipient, string $language): string
    {
        $fallback = $language === 'en' ? 'Dear Hiring Manager,' : 'Kepada Tim Rekrutmen,';

        if ($recipient === null) {
            return $fallback;
        }

        // Generic placeholders the model invents.
        $placeholders = ['hiring manager', 'tim rekrutmen', 'recruiter', 'perekrut', 'hrd', 'hr team', 'tim hr',
            '[name]', '{name}', 'nama perekrut', 'mr.', 'mrs.', 'ms.', 'dear sir or madam', 'kepada yth'];

        $lower = mb_strtolower($recipient);

        foreach ($placeholders as $placeholder) {
            if (str_contains($lower, $placeholder)) {
                return $fallback;
            }
        }

        return $recipient;
    }

    /**
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
     * Flattened snapshot text used to verify that numbers were actually present.
     */
    private static function sourceText(array $snapshot): string
    {
        return mb_strtolower(json_encode($snapshot, JSON_UNESCAPED_UNICODE) ?: '');
    }

    /**
     * Flag numbers a bullet claims that never appear in the profile.
     *
     * @return list<string>
     */
    private static function metricFlags(string $text, string $sourceText): array
    {
        $flags = [];

        if (preg_match_all(self::METRIC_PATTERN, $text, $matches) === 0) {
            return [];
        }

        foreach (array_unique($matches[1]) as $number) {
            $clean = trim($number);

            // Single digits ("3 junior engineers" is still a claim) but skip years and list numbering.
            if (strlen(preg_replace('/\D/', '', $clean)) < 1) {
                continue;
            }

            if (str_contains($sourceText, mb_strtolower($clean))) {
                continue;
            }

            $flags[] = "Angka \"{$clean}\" tidak ditemukan di profil";
        }

        return $flags;
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private static function bullets(mixed $bullets, string $sourceText): array
    {
        $items = [];
        $flags = [];

        foreach (self::strings($bullets, self::MAX_BULLETS) as $bullet) {
            $items[] = $bullet;
            $flags = [...$flags, ...self::metricFlags($bullet, $sourceText)];
        }

        return [$items, $flags];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private static function findSource(array $rows, string $organization, string $role): ?array
    {
        $org = mb_strtolower(trim($organization));
        $title = mb_strtolower(trim($role));

        foreach ($rows as $row) {
            if (mb_strtolower(trim((string) ($row['organization'] ?? ''))) === $org
                && mb_strtolower(trim((string) ($row['job_title'] ?? ''))) === $title) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private static function findByName(array $rows, string $name): ?array
    {
        $needle = mb_strtolower(trim($name));

        foreach ($rows as $row) {
            if (mb_strtolower(trim((string) ($row['name'] ?? $row['institution'] ?? ''))) === $needle) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private static function findSkill(array $rows, string $name): ?array
    {
        $needle = mb_strtolower(trim($name));

        foreach ($rows as $row) {
            if (mb_strtolower(trim((string) ($row['name'] ?? ''))) === $needle) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param  array<string, true>  $known
     * @return list<string>
     */
    private static function refs(mixed $refs, array $known): array
    {
        if (! is_array($refs)) {
            return [];
        }

        $valid = [];

        foreach (array_slice(array_values($refs), 0, 6) as $ref) {
            if (! is_string($ref)) {
                continue;
            }

            $normalised = trim(mb_strtolower($ref), '[] ');

            if (isset($known[$normalised]) && ! in_array($normalised, $valid, true)) {
                $valid[] = $normalised;
            }
        }

        return $valid;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $rawJson): array
    {
        $decoded = json_decode($rawJson, true);

        if (! is_array($decoded) && preg_match('/\{.*\}/s', $rawJson, $m)) {
            $decoded = json_decode($m[0], true);
        }

        if (! is_array($decoded)) {
            throw new \RuntimeException('invalid_output');
        }

        return $decoded;
    }

    /**
     * @return list<array<string, mixed>|string>
     */
    private static function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value, int $max): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];

        foreach (array_slice(array_values($value), 0, $max) as $item) {
            $text = self::text($item, 1000);

            if ($text !== null) {
                $out[] = $text;
            }
        }

        return $out;
    }

    private static function text(mixed $value, int $max): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
