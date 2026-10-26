<?php

namespace App\Jobs;

use App\Models\ProfileDocument;
use App\Services\Ai\CvExtractionProvider;
use App\Services\CvTextExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Reads a stored CV file, asks the configured provider to structure it, and
 * stores the validated draft on the document. Runs on the queue because the
 * provider call can take a while.
 */
class ExtractCvDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public int $documentId) {}

    public function handle(CvTextExtractor $extractor, CvExtractionProvider $provider): void
    {
        $document = ProfileDocument::find($this->documentId);

        if ($document === null || $document->isFinished()) {
            return;
        }

        $document->update([
            'extraction_status' => ProfileDocument::STATUS_PROCESSING,
            'extraction_error_code' => null,
        ]);

        try {
            $absolutePath = Storage::disk('local')->path($document->storage_path);

            if (! is_file($absolutePath)) {
                $document->markFailed('file_missing');

                return;
            }

            $text = $extractor->extract($absolutePath);

            if (mb_strlen($text) < 100) {
                // Scanned PDF or an almost empty file: report, do not guess.
                $document->markFailed('no_text');

                return;
            }

            $draft = $provider->extractDraft($text);

            $document->update([
                'extraction_status' => ProfileDocument::STATUS_COMPLETED,
                'extraction_error_code' => null,
                'draft' => $draft,
                'model_name' => $provider->name().':'.config('services.ai.model', ''),
            ]);
        } catch (\RuntimeException $e) {
            $document->markFailed($this->errorCode($e));
        } catch (\Throwable $e) {
            Log::warning('CV extraction job failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            $document->markFailed('extraction_failed');
        }
    }

    /** Providers throw RuntimeException('code') with a known code; anything else is a generic failure. */
    private function errorCode(\RuntimeException $e): string
    {
        $message = $e->getMessage();

        return array_key_exists($message, ProfileDocument::ERROR_MESSAGES)
            ? $message
            : 'extraction_failed';
    }
}
