<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProfileSection;
use App\Http\Requests\ProfileReorderRequest;
use App\Http\Requests\ProfileEducationRequest;
use App\Models\ProfileEducation;
use App\Services\CareerProfileService;
use App\Services\ProfileSortOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileEducationController extends Controller
{
    use AuthorizesProfileSection;

    public function __construct(
        private readonly CareerProfileService $profiles,
        private readonly ProfileSortOrderService $sorter,
    ) {
    }

    public function store(ProfileEducationRequest $request): RedirectResponse
    {
        $profile = $this->profiles->forUser($request->user());

        $profile->educations()->create([
            ...$request->validated(),
            'sort_order' => (int) $profile->educations()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Pendidikan ditambahkan.');
    }

    public function update(ProfileEducationRequest $request, ProfileEducation $education): RedirectResponse
    {
        $this->ensureOwned($this->profileFor($request->user()), $education);

        $education->update($request->validated());

        return back()->with('success', 'Pendidikan diperbarui.');
    }

    public function destroy(Request $request, ProfileEducation $education): RedirectResponse
    {
        $this->ensureOwned($this->profileFor($request->user()), $education);

        $education->delete();

        return back()->with('success', 'Pendidikan dihapus.');
    }

    public function reorder(ProfileReorderRequest $request): RedirectResponse
    {
        $profile = $this->profileFor($request->user());

        $this->sorter->reorder($profile->educations()->get(), $request->validated('ids'));

        return back()->with('success', 'Urutan pendidikan disimpan.');
    }
}
