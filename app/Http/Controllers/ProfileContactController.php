<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProfileSection;
use App\Http\Requests\ProfileContactRequest;
use App\Services\CareerProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileContactController extends Controller
{
    use AuthorizesProfileSection;

    public function __construct(private readonly CareerProfileService $profiles)
    {
    }

    public function update(ProfileContactRequest $request): RedirectResponse
    {
        $profile = $this->profiles->forUser($request->user());

        $profile->contact()->updateOrCreate([], $request->validated());

        return back()->with('success', 'Kontak diperbarui.');
    }
}
