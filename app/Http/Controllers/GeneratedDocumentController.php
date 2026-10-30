<?php

namespace App\Http\Controllers;

use App\Http\Requests\AutosaveDocumentRequest;
use App\Http\Requests\GenerateDocumentRequest;
use App\Http\Requests\ReorderDocumentSectionsRequest;
use App\Http\Requests\UpdateGeneratedDocumentRequest;
use App\Jobs\GenerateDocumentJob;
use App\Models\DocumentExport;
use App\Models\DocumentRevision;
use App\Models\GeneratedDocument;
use App\Models\JobApplication;
use App\Services\DocumentEditorService;
use App\Services\DocumentGenerationService;
use App\Services\DocumentTemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GeneratedDocumentController extends Controller
{
    public function __construct(
        private readonly DocumentGenerationService $documents,
        private readonly DocumentEditorService $editor,
    ) {}

    /**
     * All documents across the user's applications.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $typeFilter = $request->query('type');

        $query = GeneratedDocument::query()
            ->whereHas('jobApplication', fn ($q) => $q->where('user_id', $user->id))
            ->with(['jobApplication:id,company_name,job_title,user_id']);

        if (is_string($typeFilter) && array_key_exists($typeFilter, GeneratedDocument::TYPES)) {
            $query->where('type', $typeFilter);
        }

        $documents = $query->latest('id')->get()->map(fn (GeneratedDocument $document) => [
            'id' => $document->id,
            'type' => $document->type,
            'type_label' => $document->typeLabel(),
            'title' => $document->title,
            'version' => $document->version,
            'language' => $document->language,
            'generation_status' => $document->generation_status,
            'error_message' => $document->errorMessage(),
            'company_name' => $document->jobApplication?->company_name,
            'job_title' => $document->jobApplication?->job_title,
            'application_id' => $document->job_application_id,
            'unverified_count' => count($document->unverifiedClaimList()),
            'created_at' => $document->created_at,
        ]);

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
            'types' => GeneratedDocument::TYPES,
            'filters' => ['type' => $typeFilter],
        ]);
    }

    /**
     * Queue a new version for an application.
     */
    public function store(GenerateDocumentRequest $request, JobApplication $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $document = $this->documents->queue(
            $application,
            $request->validated('type'),
            $request->safe()->only(['language', 'tone', 'recipient_name']),
        );

        GenerateDocumentJob::dispatch($document->id);

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'Dokumen sedang dibuat. Halaman ini akan diperbarui otomatis.');
    }

    public function show(GeneratedDocument $document): Response
    {
        $this->authorize('view', $document);

        $document->load(['jobApplication', 'analysis']);

        return Inertia::render('Documents/Show', [
            'document' => $this->payload($document),
            'application' => [
                'id' => $document->jobApplication->id,
                'company_name' => $document->jobApplication->company_name,
                'job_title' => $document->jobApplication->job_title,
                'language' => $document->jobApplication->language,
            ],
            'sections' => GeneratedDocument::RESUME_SECTIONS,
            'tones' => GeneratedDocument::TONE_LABELS,
            'types' => GeneratedDocument::TYPES,
            'templates' => GeneratedDocument::TEMPLATES,
            'revisions' => $document->revisions()->limit(30)->get()->map(fn (DocumentRevision $revision) => [
                'id' => $revision->id,
                'source' => $revision->source,
                'source_label' => $revision->sourceLabel(),
                'word_count' => $revision->word_count,
                'created_at' => $revision->created_at,
            ]),
            'word_limit' => $this->editor->wordLimit($document),
            'overflow_warning' => $this->editor->overflowWarning($document),
            'exports' => $document->exports()->limit(20)->get()->map(fn (DocumentExport $export) => [
                'id' => $export->id,
                'format' => $export->format,
                'format_label' => $export->formatLabel(),
                'filename' => $export->filename,
                'file_size' => $export->file_size,
                'status' => $export->status,
                'created_at' => $export->created_at,
            ]),
            'export_formats' => DocumentExport::FORMATS,
        ]);
    }

    /**
     * Persist user edits to the current version.
     */
    public function update(UpdateGeneratedDocumentRequest $request, GeneratedDocument $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $content = $request->validated('content');
        $existing = $document->content_json ?? [];

        // User edits are authoritative for the fields they send; guardrails meta is preserved.
        $merged = [
            ...$existing,
            ...$content,
            'unverified_claims' => $existing['unverified_claims'] ?? [],
        ];

        $this->documents->updateContent($document, $merged);

        return back()->with('success', 'Perubahan dokumen disimpan.');
    }

    /**
     * Autosave endpoint: JSON in, JSON out, no page reload.
     */
    public function autosave(AutosaveDocumentRequest $request, GeneratedDocument $document): JsonResponse
    {
        $this->authorize('update', $document);

        $existing = $document->content_json ?? [];
        $content = [
            ...$existing,
            ...$request->validated('content'),
            'unverified_claims' => $existing['unverified_claims'] ?? [],
        ];

        if ($request->filled('template_key')) {
            $document->update(['template_key' => $request->validated('template_key')]);
        }

        $saved = $this->editor->save(
            $document,
            $content,
            $request->validated('section_order'),
            DocumentRevision::SOURCE_AUTO,
        );

        return response()->json([
            'status' => 'saved',
            'saved_at' => $saved->last_saved_at?->toIso8601String(),
            'saved_at_label' => $saved->last_saved_at?->format('H:i'),
            'word_count' => $saved->word_count,
            'word_limit' => $this->editor->wordLimit($saved),
            'overflow_warning' => $this->editor->overflowWarning($saved),
        ]);
    }

    /**
     * Persist the resume section order.
     */
    public function reorder(ReorderDocumentSectionsRequest $request, GeneratedDocument $document): RedirectResponse
    {
        $this->authorize('update', $document);

        abort_if($document->type !== GeneratedDocument::TYPE_RESUME, 422, 'Urutan bagian hanya untuk CV.');

        $this->editor->save(
            $document,
            $document->content_json ?? [],
            $request->validated('section_order'),
            DocumentRevision::SOURCE_MANUAL,
        );

        return back()->with('success', 'Urutan bagian disimpan.');
    }

    /**
     * Restore a past revision as a new revision (history is append-only).
     */
    public function restore(Request $request, GeneratedDocument $document, DocumentRevision $revision): RedirectResponse
    {
        $this->authorize('update', $document);

        abort_unless($revision->generated_document_id === $document->id, 404);

        $this->editor->restore($document, $revision);

        return back()->with('success', 'Versi lama berhasil dipulihkan.');
    }

    /**
     * Server-rendered ATS preview (same markup Phase 7 will export).
     */
    public function preview(GeneratedDocument $document): \Illuminate\Http\Response
    {
        $this->authorize('view', $document);

        $document->load(['jobApplication.careerProfile.contact', 'jobApplication.user']);

        return response(app(DocumentTemplateRenderer::class)->renderPage($document), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    /**
     * Re-run generation for one section (or the whole document when no section is given).
     */
    public function regenerate(Request $request, GeneratedDocument $document): RedirectResponse
    {
        $this->authorize('regenerate', $document);

        $section = $request->validate([
            'section' => ['nullable', 'string', 'in:summary,skills,experiences,projects,education,certifications,languages,opening,body_paragraphs,closing,recipient_line,signature'],
        ])['section'] ?? null;

        $new = $this->documents->queue(
            $document->jobApplication,
            $document->type,
            ['language' => $document->language, 'tone' => $document->tone],
            parent: $document,
            section: $section,
        );

        GenerateDocumentJob::dispatch($new->id);

        $message = $section === null
            ? 'Dokumen baru sedang dibuat.'
            : 'Bagian '.$section.' sedang dibuat ulang; bagian lain dipertahankan.';

        return redirect()->route('documents.show', $new)->with('success', $message);
    }

    /**
     * Polling endpoint for the generation status.
     */
    public function status(GeneratedDocument $document): JsonResponse
    {
        $this->authorize('view', $document);

        return response()->json([
            'id' => $document->id,
            'status' => $document->generation_status,
            'error_message' => $document->errorMessage(),
            'unverified_count' => count($document->unverifiedClaimList()),
        ]);
    }

    public function destroy(GeneratedDocument $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $applicationId = $document->job_application_id;
        $document->delete();

        return redirect()
            ->route('applications.show', $applicationId)
            ->with('success', 'Dokumen dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(GeneratedDocument $document): array
    {
        return [
            'id' => $document->id,
            'type' => $document->type,
            'type_label' => $document->typeLabel(),
            'title' => $document->title,
            'version' => $document->version,
            'language' => $document->language,
            'tone' => $document->tone,
            'generation_status' => $document->generation_status,
            'error_message' => $document->errorMessage(),
            'model_name' => $document->model_name,
            'created_at' => $document->created_at,
            'content' => $document->isCompleted() ? $document->content_json : null,
            'unverified_claims' => $document->unverifiedClaimList(),
            'application_id' => $document->job_application_id,
            'section_order' => $document->resolvedSectionOrder(),
            'word_count' => $document->word_count,
            'last_saved_at' => $document->last_saved_at?->toIso8601String(),
            'template_key' => $document->template_key,
        ];
    }
}
