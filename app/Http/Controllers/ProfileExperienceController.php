<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProfileSection;
use App\Http\Requests\ProfileExperienceRequest;
use App\Http\Requests\ProfileReorderRequest;
use App\Models\ProfileExperience;
use App\Services\CareerProfileService;
use App\Services\ProfileSortOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileExperienceController extends Controller
{
    use AuthorizesProfileSection;

    public function __construct(
        private readonly CareerProfileService $profiles,
        private readonly ProfileSortOrderService $sorter,
    ) {
    }

    public function store(ProfileExperienceRequest $request): RedirectResponse
    {
        $profile = $this->profiles->forUser($request->user());

        $profile->experiences()->create([
            ...$request->validated(),
            'sort_order' => (int) $profile->experiences()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Pengalaman ditambahkan.');
    }

    public function update(ProfileExperienceRequest $request, ProfileExperience $experience): RedirectResponse
    {
        $this->ensureOwned($this->profileFor($request->user()), $experience);

        $experience->update($request->validated());

        return back()->with('success', 'Pengalaman diperbarui.');
    }

    public function destroy(Request $request, ProfileExperience $experience): RedirectResponse
    {
        $this->ensureOwned($this->profileFor($request->user()), $experience);

        $experience->delete();

        return back()->with('success', 'Pengalaman dihapus.');
    }

    public function reorder(ProfileReorderRequest $request): RedirectResponse
    {
        $profile = $this->profileFor($request->user());

        $this->sorter->reorder($profile->experiences()->get(), $request->validated('ids'));

        return back()->with('success', 'Urutan pengalaman disimpan.');
    }
}
