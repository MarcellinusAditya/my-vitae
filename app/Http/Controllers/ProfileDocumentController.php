<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProfileSection;
use App\Http\Requests\ImportProfileDocumentRequest;
use App\Http\Requests\StoreProfileDocumentRequest;
use App\Jobs\ExtractCvDocumentJob;
use App\Models\ProfileDocument;
use App\Services\CareerProfileService;
use App\Services\CvProfileMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileDocumentController extends Controller
{
    use AuthorizesProfileSection;

    public function __construct(
        private readonly CareerProfileService $profiles,
        private readonly CvProfileMergeService $merger,
    ) {}

    /**
     * Import page: upload form plus this user's document history.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $documents = $user->documents()
            ->get(['id', 'original_filename', 'file_size', 'extraction_status', 'extraction_error_code', 'applied_at', 'created_at'])
            ->map(fn (ProfileDocument $document) => [
                ...$document->only(['id', 'original_filename', 'file_size', 'extraction_status', 'applied_at', 'created_at']),
                'error_message' => $document->errorMessage(),
            ]);

        return Inertia::render('CareerProfile/Import/Index', [
            'documents' => $documents,
            'maxFileSizeMb' => (int) (StoreProfileDocumentRequest::MAX_KILOBYTES / 1024),
        ]);
    }

    public function store(StoreProfileDocumentRequest $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $this->profiles->forUser($user);
        $file = $request->file('document');

        // Random storage name on the private disk; the original name is only metadata.
        $path = $file->store('cv-uploads', 'local');

        $document = $user->documents()->create([
            'career_profile_id' => $profile->id,
            'original_filename' => Str::limit($file->getClientOriginalName(), 255, ''),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize() ?: 0,
            'extraction_status' => ProfileDocument::STATUS_PENDING,
        ]);

        ExtractCvDocumentJob::dispatch($document->id);

        return redirect()
            ->route('career-profile.import.show', $document)
            ->with('success', 'File diterima. Ekstraksi sedang diproses.');
    }

    /**
     * Extraction status (JSON, polled from the UI) or the review page (Inertia).
     */
    public function show(Request $request, ProfileDocument $document): Response|JsonResponse
    {
        $this->authorize('view', $document);

        if ($request->boolean('status')) {
            return response()->json([
                'status' => $document->extraction_status,
                'error_message' => $document->errorMessage(),
            ]);
        }

        return Inertia::render('CareerProfile/Import/Review', [
            'document' => [
                'id' => $document->id,
                'original_filename' => $document->original_filename,
                'file_size' => $document->file_size,
                'extraction_status' => $document->extraction_status,
                'error_message' => $document->errorMessage(),
                'model_name' => $document->model_name,
                'applied_at' => $document->applied_at,
                'created_at' => $document->created_at,
            ],
            'draft' => $document->hasDraft() ? $document->draft['sections'] : null,
            'sections' => ProfileDocument::DRAFT_SECTIONS,
            'profile' => [
                'has_contact' => $document->careerProfile?->contact()->exists() ?? false,
                'has_summary' => filled($document->careerProfile?->summary),
            ],
        ]);
    }

    /**
     * Apply the reviewed (and user-edited) draft rows to the profile.
     */
    public function confirm(ImportProfileDocumentRequest $request, ProfileDocument $document): RedirectResponse
    {
        $this->authorize('confirm', $document);

        abort_unless($document->hasDraft(), 422, 'Tidak ada hasil ekstraksi untuk diimpor.');

        $profile = $this->profileFor($request->user());

        $applied = $this->merger->apply($profile, $request->validated('sections'));

        $document->update(['applied_at' => now()]);

        return redirect()
            ->route('career-profile.show')
            ->with('success', $this->appliedMessage($applied));
    }

    /**
     * Download the original uploaded file (owner only, private disk).
     */
    public function download(Request $request, ProfileDocument $document): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);

        return Storage::disk('local')->download($document->storage_path, $document->original_filename);
    }

    public function destroy(Request $request, ProfileDocument $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        // Deleting the model also removes the stored file (see ProfileDocument::booted).
        $document->delete();

        return back()->with('success', 'Dokumen sumber dihapus.');
    }

    /**
     * @param  array<string, int>  $applied
     */
    private function appliedMessage(array $applied): string
    {
        if ($applied === []) {
            return 'Tidak ada data yang diimpor.';
        }

        $total = array_sum($applied);

        return "Impor selesai: {$total} data ditambahkan ke profil karier.";
    }
}
