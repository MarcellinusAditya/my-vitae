<?php

namespace App\Services\Ai;

/**
 * Generates tailored resumes and cover letters from a verified profile snapshot.
 *
 * Implementations must not invent facts; the returned payload is always
 * re-validated by DocumentFactValidator before it is stored.
 */
interface DocumentGenerationProvider
{
    public function name(): string;

    /**
     * @param  array<string, mixed>  $profileSnapshot
     * @param  array<string, mixed>  $jobSnapshot
     * @param  array<string, mixed>  $preferences  language, tone, length, writing_style, analysis requirements
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    public function generateResume(array $profileSnapshot, array $jobSnapshot, array $preferences): array;

    /**
     * @param  array<string, mixed>  $profileSnapshot
     * @param  array<string, mixed>  $jobSnapshot
     * @param  array<string, mixed>  $preferences
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    public function generateCoverLetter(array $profileSnapshot, array $jobSnapshot, array $preferences): array;
}
