<?php

namespace App\Policies;

use App\Models\DocumentExport;
use App\Models\User;

class DocumentExportPolicy
{
    public function download(User $user, DocumentExport $export): bool
    {
        return $export->document?->jobApplication?->user_id === $user->id;
    }

    public function delete(User $user, DocumentExport $export): bool
    {
        return $this->download($user, $export);
    }
}
