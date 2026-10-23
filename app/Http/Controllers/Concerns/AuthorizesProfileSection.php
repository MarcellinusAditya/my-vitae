<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CareerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesProfileSection
{
    /**
     * Resolve the signed-in user's profile or fail.
     */
    protected function profileFor(User $user): CareerProfile
    {
        $profile = $user->careerProfile;

        abort_if($profile === null, 404);

        return $profile;
    }

    /**
     * Abort with 403 when a bound model does not belong to the user's profile.
     */
    protected function ensureOwned(CareerProfile $profile, Model $model): void
    {
        abort_unless(
            (int) $model->getAttribute('career_profile_id') === (int) $profile->id,
            403,
        );
    }
}
