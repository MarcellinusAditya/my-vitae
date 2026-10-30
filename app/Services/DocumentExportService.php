<?php

namespace App\Services;

use App\Models\DocumentExport;
use App\Models\GeneratedDocument;
use App\Services\Exports\DocxExporter;
use App\Services\Exports\PdfExporter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Creates export files on the private disk, prunes old ones, and hands out
 * display metadata for the UI.
 */
class DocumentExportService
{
    /** How many exports of one format to keep per document. */
    public const KEEP_PER_FORMAT = 5;

    public function __construct(
        private readonly PdfExporter $pdf,
        private readonly DocxExporter $docx,
    ) {}

    /**
     * Render and store an export of the given format.
     *
     * @throws \RuntimeException with a DocumentExport error code
     */
    public function create(GeneratedDocument $document, string $format): DocumentExport
    {
        if (! array_key_exists($format, DocumentExport::FORMATS)) {
            throw new \RuntimeException('unsupported_format');
        }

        if (! $document->isCompleted() || ! is_array($document->content_json) || $document->content_json === []) {
            throw new \RuntimeException('document_not_ready');
        }

        try {
            $bytes = $format === DocumentExport::FORMAT_PDF
                ? $this->pdf->render($document)
                : $this->docx->render($document);
        } catch (\Throwable $e) {
            throw $e instanceof \RuntimeException && $e->getMessage() === 'render_failed'
                ? $e
                : new \RuntimeException('render_failed', 0, $e);
        }

        $filename = $this->filename($document, $format);
        $path = 'document-exports/'.$document->id.'/'.Str::random(24).'-'.$filename;

        Storage::disk('local')->put($path, $bytes);

        $export = $document->exports()->create([
            'format' => $format,
            'filename' => $filename,
            'storage_path' => $path,
            'file_size' => strlen($bytes),
            'status' => DocumentExport::STATUS_COMPLETED,
            'content_hash' => hash('sha256', (string) json_encode($document->content_json)),
        ]);

        $this->prune($document, $format);

        return $export;
    }

    /**
     * Keep the newest N exports of a format; delete older rows + files.
     */
    public function prune(GeneratedDocument $document, string $format): int
    {
        $stale = $document->exports()
            ->where('format', $format)
            ->orderByDesc('id')
            ->skip(self::KEEP_PER_FORMAT)
            ->take(50)
            ->get();

        $deleted = 0;

        foreach ($stale as $export) {
            $export->delete(); // booted() removes the file
            $deleted++;
        }

        return $deleted;
    }

    /**
     * File name without characters that break downloads on any OS.
     */
    public function filename(GeneratedDocument $document, string $format): string
    {
        $application = $document->jobApplication;

        $base = implode('-', array_filter([
            $document->type === GeneratedDocument::TYPE_RESUME ? 'cv' : 'cover-letter',
            $application?->job_title,
            $application?->company_name,
            'v'.$document->version,
            now()->format('Ymd'),
        ]));

        $slug = Str::slug($base) ?: 'document';

        return mb_substr($slug, 0, 120).'.'.$format;
    }
}
