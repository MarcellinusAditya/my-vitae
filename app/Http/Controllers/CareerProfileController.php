<?php

namespace App\Http\Controllers;

use App\Http\Requests\CareerProfileUpdateRequest;
use App\Services\CareerProfileService;
use App\Services\ProfileCompletenessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CareerProfileController extends Controller
{
    public function __construct(
        private readonly CareerProfileService $profiles,
        private readonly ProfileCompletenessService $completeness,
    ) {
    }

    public function show(Request $request): Response
    {
        $profile = $this->profiles->forUser($request->user());

        return Inertia::render('CareerProfile/Index', $this->profiles->toPagePayload($profile));
    }

    public function update(CareerProfileUpdateRequest $request): RedirectResponse
    {
        $profile = $this->profiles->forUser($request->user());

        $this->authorize('update', $profile);

        $profile->update($request->validated());

        return back()->with('success', 'Profil karier diperbarui.');
    }
}
