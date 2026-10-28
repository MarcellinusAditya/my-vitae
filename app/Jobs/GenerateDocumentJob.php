<?php

namespace App\Jobs;

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

            $document->update([
                'generation_status' => GeneratedDocument::STATUS_COMPLETED,
                'error_code' => null,
                'content_json' => $result,
                'model_name' => $provider->name().':'.config('services.ai.model', ''),
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
}
