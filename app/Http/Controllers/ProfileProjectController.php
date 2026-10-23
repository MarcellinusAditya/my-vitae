<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProfileSection;
use App\Http\Requests\ProfileReorderRequest;
use App\Http\Requests\ProfileProjectRequest;
use App\Models\ProfileProject;
use App\Services\CareerProfileService;
use App\Services\ProfileSortOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileProjectController extends Controller
{
    use AuthorizesProfileSection;

    public function __construct(
        private readonly CareerProfileService $profiles,
        private readonly ProfileSortOrderService $sorter,
    ) {
    }

    public function store(ProfileProjectRequest $request): RedirectResponse
    {
        $profile = $this->profiles->forUser($request->user());

        $profile->projects()->create([
            ...$request->validated(),
            'sort_order' => (int) $profile->projects()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Proyek ditambahkan.');
    }

    public function update(ProfileProjectRequest $request, ProfileProject $project): RedirectResponse
    {
        $this->ensureOwned($this->profileFor($request->user()), $project);

        $project->update($request->validated());

        return back()->with('success', 'Proyek diperbarui.');
    }

    public function destroy(Request $request, ProfileProject $project): RedirectResponse
    {
        $this->ensureOwned($this->profileFor($request->user()), $project);

        $project->delete();

        return back()->with('success', 'Proyek dihapus.');
    }

    public function reorder(ProfileReorderRequest $request): RedirectResponse
    {
        $profile = $this->profileFor($request->user());

        $this->sorter->reorder($profile->projects()->get(), $request->validated('ids'));

        return back()->with('success', 'Urutan proyek disimpan.');
    }
}
