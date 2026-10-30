<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateDocumentExportRequest;
use App\Models\DocumentExport;
use App\Models\GeneratedDocument;
use App\Services\DocumentExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentExportController extends Controller
{
    public function __construct(private readonly DocumentExportService $exports) {}

    /**
     * Render and store a new export for the document.
     */
    public function store(CreateDocumentExportRequest $request, GeneratedDocument $document): RedirectResponse
    {
        $this->authorize('update', $document);

        try {
            $export = $this->exports->create($document, $request->validated('format'));
        } catch (\RuntimeException $e) {
            $code = array_key_exists($e->getMessage(), DocumentExport::ERROR_MESSAGES)
                ? $e->getMessage()
                : 'render_failed';

            return back()->with('error', DocumentExport::ERROR_MESSAGES[$code]);
        }

        return back()->with('success', "File {$export->formatLabel()} siap diunduh.");
    }

    /**
     * Owner-only download from the private disk.
     */
    public function download(DocumentExport $export): StreamedResponse
    {
        $this->authorize('download', $export);

        abort_unless($export->isCompleted(), 404);
        abort_unless(Storage::disk('local')->exists($export->storage_path), 404);

        return Storage::disk('local')->download($export->storage_path, $export->filename, [
            'Content-Type' => $export->format === DocumentExport::FORMAT_PDF
                ? 'application/pdf'
                : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function destroy(DocumentExport $export): RedirectResponse
    {
        $this->authorize('delete', $export);

        $documentId = $export->generated_document_id;
        $export->delete(); // booted() removes the stored file

        return redirect()
            ->route('documents.show', $documentId)
            ->with('success', 'File ekspor dihapus.');
    }
}
