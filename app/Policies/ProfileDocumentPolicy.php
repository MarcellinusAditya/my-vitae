<?php

namespace App\Policies;

use App\Models\ProfileDocument;
use App\Models\User;

class ProfileDocumentPolicy
{
    public function view(User $user, ProfileDocument $document): bool
    {
        return $document->user_id === $user->id;
    }

    public function confirm(User $user, ProfileDocument $document): bool
    {
        return $document->user_id === $user->id;
    }

    public function delete(User $user, ProfileDocument $document): bool
    {
        return $document->user_id === $user->id;
    }
}
