<?php

namespace App\Http\Controllers;

use App\Http\Requests\JobApplicationRequest;
use App\Http\Requests\UpdateJobStatusRequest;
use App\Jobs\AnalyzeJobApplicationJob;
use App\Models\GeneratedDocument;
use App\Models\JobApplication;
use App\Models\JobRequirementAnalysis;
use App\Services\CareerProfileService;
use App\Services\JobApplicationService;
use App\Services\UserDataCleanupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobApplicationController extends Controller
{
    public function __construct(
        private readonly CareerProfileService $profiles,
        private readonly JobApplicationService $applications,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $statusFilter = $request->query('status');

        $query = $user->jobApplications()
            ->with(['analyses' => fn ($relation) => $relation->select('id', 'job_application_id', 'status', 'analysis_json')->latest('id')]);

        if (is_string($statusFilter) && array_key_exists($statusFilter, JobApplication::STATUSES)) {
            $query->where('status', $statusFilter);
        }

        $applications = $query->latest()->get()->map(fn (JobApplication $application) => [
            'id' => $application->id,
            'company_name' => $application->company_name,
            'job_title' => $application->job_title,
            'job_location' => $application->job_location,
            'status' => $application->status,
            'status_label' => $application->statusLabel(),
            'created_at' => $application->created_at,
            'analysis' => ($latest = $application->analyses->first()) === null ? null : [
                'id' => $latest->id,
                'status' => $latest->status,
                ...$latest->counts(),
            ],
        ]);

        return Inertia::render('Applications/Index', [
            'applications' => $applications,
            'statuses' => JobApplication::STATUSES,
            'filters' => ['status' => $statusFilter],
            'counts' => [
                'total' => $user->jobApplications()->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Applications/Create', [
            'languages' => JobApplication::LANGUAGES,
            'defaultLanguage' => $this->profiles->forUser($request->user())->default_language,
        ]);
    }

    public function store(JobApplicationRequest $request): RedirectResponse
    {
        $profile = $this->profiles->forUser($request->user());

        $application = $this->applications->create($profile, [
            ...$request->validated(),
            'status' => JobApplication::STATUS_DRAFT,
        ]);

        $this->dispatchAnalysis($application);

        return redirect()
            ->route('applications.show', $application)
            ->with('success', 'Lamaran dibuat. Analisis kecocokan sedang diproses.');
    }

    public function show(Request $request, JobApplication $application): Response
    {
        $this->authorize('view', $application);

        $application->load('careerProfile');
        $analysis = $application->analyses()->first();

        return Inertia::render('Applications/Show', [
            'application' => [
                'id' => $application->id,
                'company_name' => $application->company_name,
                'job_title' => $application->job_title,
                'job_location' => $application->job_location,
                'job_description' => $application->job_description,
                'job_qualifications' => $application->job_qualifications,
                'language' => $application->language,
                'notes' => $application->notes,
                'status' => $application->status,
                'status_label' => $application->statusLabel(),
                'created_at' => $application->created_at,
                'updated_at' => $application->updated_at,
                'documents' => $application->generatedDocuments()
                    ->get()
                    ->map(fn (GeneratedDocument $document) => [
                        'id' => $document->id,
                        'type' => $document->type,
                        'type_label' => $document->typeLabel(),
                        'version' => $document->version,
                        'generation_status' => $document->generation_status,
                    ]),
            ],
            'analysis' => $analysis === null ? null : [
                'id' => $analysis->id,
                'status' => $analysis->status,
                'error_message' => $analysis->errorMessage(),
                'model_name' => $analysis->model_name,
                'created_at' => $analysis->created_at,
                'result' => $analysis->isCompleted() ? $analysis->analysis_json : null,
                ...$analysis->counts(),
            ],
            'statuses' => JobApplication::STATUSES,
            'matches' => [
                'matched' => JobRequirementAnalysis::MATCH_MATCHED,
                'partial' => JobRequirementAnalysis::MATCH_PARTIAL,
                'not_evidenced' => JobRequirementAnalysis::MATCH_NOT_EVIDENCED,
            ],
            'hasProfile' => $application->careerProfile !== null,
        ]);
    }

    public function edit(JobApplication $application): Response
    {
        $this->authorize('update', $application);

        return Inertia::render('Applications/Edit', [
            'application' => $application->only([
                'id', 'company_name', 'job_title', 'job_location',
                'job_description', 'job_qualifications', 'language', 'notes', 'status',
            ]),
            'languages' => JobApplication::LANGUAGES,
        ]);
    }

    public function update(JobApplicationRequest $request, JobApplication $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $application->update($request->validated());

        return redirect()
            ->route('applications.show', $application)
            ->with('success', 'Lamaran diperbarui.');
    }

    public function updateStatus(UpdateJobStatusRequest $request, JobApplication $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $application->update(['status' => $request->validated('status')]);

        return back()->with('success', 'Status lamaran diperbarui.');
    }

    public function analyze(JobApplication $application): RedirectResponse
    {
        $this->authorize('analyze', $application);

        $this->dispatchAnalysis($application);

        return redirect()
            ->route('applications.show', $application)
            ->with('success', 'Analisis ulang sedang diproses.');
    }

    /**
     * JSON status for the polling UI.
     */
    public function analysisStatus(JobApplication $application): JsonResponse
    {
        $this->authorize('view', $application);

        $analysis = $application->analyses()->first();

        if ($analysis === null) {
            return response()->json(['status' => null, 'error_message' => null]);
        }

        return response()->json([
            'id' => $analysis->id,
            'status' => $analysis->status,
            'error_message' => $analysis->errorMessage(),
            ...$analysis->counts(),
        ]);
    }

    public function duplicate(JobApplication $application): RedirectResponse
    {
        $this->authorize('duplicate', $application);

        $copy = $this->applications->duplicate($application);

        return redirect()
            ->route('applications.show', $copy)
            ->with('success', 'Lamaran diduplikasi sebagai draft baru.');
    }

    public function destroy(Request $request, JobApplication $application): RedirectResponse
    {
        $this->authorize('delete', $application);

        app(UserDataCleanupService::class)->deleteApplication($application);

        return redirect()
            ->route('applications.index')
            ->with('success', 'Lamaran dihapus.');
    }

    private function dispatchAnalysis(JobApplication $application): void
    {
        $analysis = $this->applications->queueAnalysis($application);

        AnalyzeJobApplicationJob::dispatch($analysis->id);
    }
}
