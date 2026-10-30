<?php

namespace App\Services;

use App\Models\GeneratedDocument;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletes user-owned data in the right order so no files are orphaned.
 *
 * Eloquent model events do not fire for query-builder deletes
 * (`$user->jobApplications()->delete()`), and a DB-level cascade cannot touch
 * the filesystem. Anything that removes an application or an account must go
 * through here.
 */
class UserDataCleanupService
{
    public function deleteApplication(JobApplication $application): void
    {
        DB::transaction(function () use ($application) {
            // Files first: document deletion removes exports (with files) and revisions.
            foreach ($application->generatedDocuments()->get() as $document) {
                $this->deleteDocument($document);
            }

            $application->analyses()->delete();
            $application->delete();
        });
    }

    public function deleteDocument(GeneratedDocument $document): void
    {
        $document->exports()->get()->each->delete(); // each delete removes its file
        $document->revisions()->delete();
        $document->delete();
    }

    /**
     * Remove every application (and its files) plus profile documents, then the account.
     */
    public function deleteUser(User $user): void
    {
        DB::transaction(function () use ($user) {
            foreach ($user->jobApplications()->get() as $application) {
                $this->deleteApplication($application);
            }

            $user->documents()->get()->each->delete();
            $user->careerProfile?->delete();
            $user->delete();
        });
    }
}
