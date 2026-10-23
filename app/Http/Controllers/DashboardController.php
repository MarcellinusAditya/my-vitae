<?php

namespace App\Http\Controllers;

use App\Services\CareerProfileService;
use App\Services\ProfileCompletenessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly CareerProfileService $profiles,
        private readonly ProfileCompletenessService $completeness,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->careerProfile;

        $completion = $profile
            ? $this->completeness->forProfile($profile)
            : [
                'filled' => 0,
                'total' => 7,
                'percentage' => 0,
                'completed' => [],
                'missing' => ['contact', 'summary', 'experiences', 'educations', 'skills', 'projects', 'certifications'],
                'next_section' => 'contact',
            ];

        return Inertia::render('Dashboard', [
            'counts' => [
                'applications' => 0,
                'documents' => 0,
            ],
            'careerProfileCompletion' => $completion,
            'hasProfile' => $profile !== null,
        ]);
    }
}
