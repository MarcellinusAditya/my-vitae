<?php

namespace App\Services\Ai;

/**
 * Provider-agnostic interface for parsing CV text into a structured draft.
 *
 * No implementation may be trusted to invent facts: implementations must
 * return only what is present in the source text, and the returned array is
 * always validated by CvDraftValidator before it is persisted.
 */
interface CvExtractionProvider
{
    /**
     * Human-readable provider id, stored on the document for observability.
     */
    public function name(): string;

    /**
     * Map raw CV text into the structured draft schema.
     *
     * @return array<string, mixed> validated by the caller
     *
     * @throws \RuntimeException on provider failure or unusable output
     */
    public function extractDraft(string $cvText): array;
}
