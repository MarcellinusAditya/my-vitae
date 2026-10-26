<?php

namespace App\Policies;

use App\Models\JobApplication;
use App\Models\User;

class JobApplicationPolicy
{
    public function view(User $user, JobApplication $application): bool
    {
        return $application->user_id === $user->id;
    }

    public function update(User $user, JobApplication $application): bool
    {
        return $application->user_id === $user->id;
    }

    public function delete(User $user, JobApplication $application): bool
    {
        return $application->user_id === $user->id;
    }

    public function analyze(User $user, JobApplication $application): bool
    {
        return $application->user_id === $user->id;
    }

    public function duplicate(User $user, JobApplication $application): bool
    {
        return $application->user_id === $user->id;
    }
}
