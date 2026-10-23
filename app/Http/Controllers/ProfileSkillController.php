<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProfileSection;
use App\Http\Requests\ProfileReorderRequest;
use App\Http\Requests\ProfileSkillRequest;
use App\Models\ProfileSkill;
use App\Services\CareerProfileService;
use App\Services\ProfileSortOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileSkillController extends Controller
{
    use AuthorizesProfileSection;

    public function __construct(
        private readonly CareerProfileService $profiles,
        private readonly ProfileSortOrderService $sorter,
    ) {
    }

    public function store(ProfileSkillRequest $request): RedirectResponse
    {
        $profile = $this->profiles->forUser($request->user());

        $profile->skills()->create([
            ...$request->validated(),
            'sort_order' => (int) $profile->skills()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Skill ditambahkan.');
    }

    public function update(ProfileSkillRequest $request, ProfileSkill $skill): RedirectResponse
    {
        $this->ensureOwned($this->profileFor($request->user()), $skill);

        $skill->update($request->validated());

        return back()->with('success', 'Skill diperbarui.');
    }

    public function destroy(Request $request, ProfileSkill $skill): RedirectResponse
    {
        $this->ensureOwned($this->profileFor($request->user()), $skill);

        $skill->delete();

        return back()->with('success', 'Skill dihapus.');
    }

    public function reorder(ProfileReorderRequest $request): RedirectResponse
    {
        $profile = $this->profileFor($request->user());

        $this->sorter->reorder($profile->skills()->get(), $request->validated('ids'));

        return back()->with('success', 'Urutan skill disimpan.');
    }
}
