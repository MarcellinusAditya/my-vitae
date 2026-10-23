<?php

namespace App\Policies;

use App\Models\CareerProfile;
use App\Models\User;

class CareerProfilePolicy
{
    public function view(User $user, CareerProfile $profile): bool
    {
        return $profile->user_id === $user->id;
    }

    public function update(User $user, CareerProfile $profile): bool
    {
        return $profile->user_id === $user->id;
    }

    public function delete(User $user, CareerProfile $profile): bool
    {
        return $profile->user_id === $user->id;
    }
}
