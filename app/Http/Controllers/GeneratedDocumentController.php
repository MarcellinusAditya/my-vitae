<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateDocumentRequest;
use App\Http\Requests\UpdateGeneratedDocumentRequest;
use App\Jobs\GenerateDocumentJob;
use App\Models\GeneratedDocument;
use App\Models\JobApplication;
use App\Services\DocumentGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GeneratedDocumentController extends Controller
{
    public function __construct(private readonly DocumentGenerationService $documents) {}

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
     * Re-run generation for one section (or the whole document when no section is given).
     */
    public function regenerate(Request $request, GeneratedDocument $document): RedirectResponse
    {
        $this->authorize('regenerate', $document);

        $section = $request->validate([
            'section' => ['nullable', 'string', 'in:summary,skills,experiences,projects,education,certifications,languages,opening,body,closing'],
        ])['section'] ?? null;

        $new = $this->documents->queue(
            $document->jobApplication,
            $document->type,
            ['language' => $document->language, 'tone' => $document->tone],
        );

        GenerateDocumentJob::dispatch($new->id);

        $message = $section === null
            ? 'Dokumen baru sedang dibuat.'
            : "Bagian {$section} sedang dibuat ulang pada versi baru.";

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
        ];
    }
}
