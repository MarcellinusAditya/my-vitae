<?php

namespace App\Policies;

use App\Models\GeneratedDocument;
use App\Models\User;

class GeneratedDocumentPolicy
{
    public function view(User $user, GeneratedDocument $document): bool
    {
        return $document->jobApplication?->user_id === $user->id;
    }

    public function update(User $user, GeneratedDocument $document): bool
    {
        return $this->view($user, $document);
    }

    public function regenerate(User $user, GeneratedDocument $document): bool
    {
        return $this->view($user, $document);
    }

    public function delete(User $user, GeneratedDocument $document): bool
    {
        return $this->view($user, $document);
    }
}
