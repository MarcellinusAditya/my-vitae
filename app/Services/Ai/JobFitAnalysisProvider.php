<?php

namespace App\Services\Ai;

/**
 * Maps job requirements against profile evidence.
 *
 * Implementations must never invent evidence: every reference has to point at
 * a row of the snapshot they were given, and the returned payload is always
 * re-validated by JobFitAnalysisValidator before it is stored.
 */
interface JobFitAnalysisProvider
{
    public function name(): string;

    /**
     * @param  array<string, mixed>  $profileSnapshot
     * @param  array<string, mixed>  $jobSnapshot
     * @return array<string, mixed>
     *
     * @throws \RuntimeException on provider failure or unusable output
     */
    public function analyze(array $profileSnapshot, array $jobSnapshot): array;
}
