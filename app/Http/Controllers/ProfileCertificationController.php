<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProfileSection;
use App\Http\Requests\ProfileReorderRequest;
use App\Http\Requests\ProfileCertificationRequest;
use App\Models\ProfileCertification;
use App\Services\CareerProfileService;
use App\Services\ProfileSortOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileCertificationController extends Controller
{
    use AuthorizesProfileSection;

    public function __construct(
        private readonly CareerProfileService $profiles,
        private readonly ProfileSortOrderService $sorter,
    ) {
    }

    public function store(ProfileCertificationRequest $request): RedirectResponse
    {
        $profile = $this->profiles->forUser($request->user());

        $profile->certifications()->create([
            ...$request->validated(),
            'sort_order' => (int) $profile->certifications()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Sertifikasi ditambahkan.');
    }

    public function update(ProfileCertificationRequest $request, ProfileCertification $certification): RedirectResponse
    {
        $this->ensureOwned($this->profileFor($request->user()), $certification);

        $certification->update($request->validated());

        return back()->with('success', 'Sertifikasi diperbarui.');
    }

    public function destroy(Request $request, ProfileCertification $certification): RedirectResponse
    {
        $this->ensureOwned($this->profileFor($request->user()), $certification);

        $certification->delete();

        return back()->with('success', 'Sertifikasi dihapus.');
    }

    public function reorder(ProfileReorderRequest $request): RedirectResponse
    {
        $profile = $this->profileFor($request->user());

        $this->sorter->reorder($profile->certifications()->get(), $request->validated('ids'));

        return back()->with('success', 'Urutan sertifikasi disimpan.');
    }
}
