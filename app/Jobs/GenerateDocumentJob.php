<?php

namespace App\Jobs;

use App\Models\DocumentRevision;
use App\Models\GeneratedDocument;
use App\Services\Ai\AiUsageLogger;
use App\Services\Ai\DocumentFactValidator;
use App\Services\Ai\DocumentGenerationProvider;
use App\Services\DocumentGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Generates a resume or cover letter for an application from its profile and
 * job snapshots. Runs on the queue because the provider call takes time.
 */
class GenerateDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public int $documentId) {}

    public function handle(DocumentGenerationProvider $provider, DocumentGenerationService $documents, AiUsageLogger $usage): void
    {
        $document = GeneratedDocument::find($this->documentId);

        if ($document === null || $document->isFinished()) {
            return;
        }

        $document->update([
            'generation_status' => GeneratedDocument::STATUS_PROCESSING,
            'error_code' => null,
        ]);

        $application = $document->jobApplication;
        $context = $documents->generationContext($document);

        if ($context === null) {
            $document->markFailed('analysis_required');

            return;
        }

        [$profileSnapshot, $jobSnapshot, $preferences] = $context;

        if ($documents->snapshots()->isEmpty($profileSnapshot)) {
            $document->markFailed('empty_profile');

            return;
        }

        $started = microtime(true);
        $operation = $document->type === GeneratedDocument::TYPE_RESUME ? 'resume_generation' : 'cover_letter_generation';

        try {
            $result = $document->type === GeneratedDocument::TYPE_RESUME
                ? $provider->generateResume($profileSnapshot, $jobSnapshot, $preferences)
                : $provider->generateCoverLetter($profileSnapshot, $jobSnapshot, $preferences);

            // Defense in depth: re-apply the fact guardrails whatever the provider returned.
            $result = $document->type === GeneratedDocument::TYPE_RESUME
                ? DocumentFactValidator::validateResume(json_encode($result) ?: '{}', $profileSnapshot)
                : DocumentFactValidator::validateCoverLetter(json_encode($result) ?: '{}', $profileSnapshot, $jobSnapshot);

            $result = $this->mergeSection($document, $result);

            $wordCount = $documents->editorWordCount($result, $document->type);

            $document->update([
                'generation_status' => GeneratedDocument::STATUS_COMPLETED,
                'error_code' => null,
                'content_json' => $result,
                'word_count' => $wordCount,
                'last_saved_at' => now(),
                'model_name' => $provider->name().':'.config('services.ai.model', ''),
            ]);

            // Snapshot the AI output as the first revision so it can be restored later.
            $document->revisions()->create([
                'content_json' => $result,
                'section_order' => $document->section_order,
                'word_count' => $wordCount,
                'source' => DocumentRevision::SOURCE_GENERATED,
            ]);

            $usage->success(
                $operation,
                $provider->name(),
                config('services.ai.model'),
                (int) ((microtime(true) - $started) * 1000),
                $application?->user,
                $application,
            );
        } catch (\RuntimeException $e) {
            $code = $this->errorCode($e);

            $document->markFailed($code);

            $usage->failed(
                $operation,
                $provider->name(),
                config('services.ai.model'),
                (int) ((microtime(true) - $started) * 1000),
                $code,
                $application?->user,
                $application,
            );
        } catch (\Throwable $e) {
            Log::warning('Document generation failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            $document->markFailed('generation_failed');

            $usage->failed(
                $operation,
                $provider->name(),
                config('services.ai.model'),
                (int) ((microtime(true) - $started) * 1000),
                'generation_failed',
                $application?->user,
                $application,
            );
        }
    }

    private function errorCode(\RuntimeException $e): string
    {
        return array_key_exists($e->getMessage(), GeneratedDocument::ERROR_MESSAGES)
            ? $e->getMessage()
            : 'generation_failed';
    }

    /**
     * For a section-scoped regeneration, keep the parent's other sections and
     * swap only the requested one. Full regeneration returns the result as-is.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function mergeSection(GeneratedDocument $document, array $result): array
    {
        $raw = $document->content_json ?? [];
        $section = $raw['_regenerate_section'] ?? null;
        $parent = $raw['_parent_content'] ?? null;

        if (! is_string($section) || ! is_array($parent)) {
            return $this->stripInternalKeys($result);
        }

        $merged = $parent;

        if (array_key_exists($section, $result)) {
            $merged[$section] = $result[$section];
        }

        // Keep the parent's guardrail metadata plus any new flags from this section.
        $merged['unverified_claims'] = array_values(array_unique([
            ...(array) ($parent['unverified_claims'] ?? []),
            ...(array) ($result['unverified_claims'] ?? []),
        ]));

        $merged['prompt_version'] = $result['prompt_version'] ?? ($parent['prompt_version'] ?? null);

        return $this->stripInternalKeys($merged);
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function stripInternalKeys(array $content): array
    {
        unset($content['_regenerate_section'], $content['_parent_content']);

        return $content;
    }
}
