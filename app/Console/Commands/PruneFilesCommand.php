<?php

namespace App\Console\Commands;

use App\Models\DocumentExport;
use App\Models\ProfileDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Enforces the retention policy in docs/RETENTION.md.
 *
 * Rows are kept as an audit trail (status, timestamps); only the files on disk
 * are removed, and the path column is cleared so nothing points at a ghost.
 */
class PruneFilesCommand extends Command
{
    protected $signature = 'app:prune-files {--days=30 : Retention window in days} {--dry-run : Report without deleting}';

    protected $description = 'Delete source CV files, old exports and failed jobs past the retention window';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        $files = 0;
        $sources = 0;
        $exports = 0;

        // 1. Uploaded CVs whose extraction has finished: the text lives in the DB,
        //    the binary is no longer needed.
        ProfileDocument::query()
            ->whereNotNull('storage_path')
            ->where('created_at', '<', $cutoff)
            ->whereIn('extraction_status', [ProfileDocument::STATUS_COMPLETED, ProfileDocument::STATUS_FAILED])
            ->chunkById(200, function ($documents) use (&$files, &$sources, $dryRun) {
                foreach ($documents as $document) {
                    if (! $dryRun && Storage::disk('local')->exists($document->storage_path)) {
                        Storage::disk('local')->delete($document->storage_path);
                    }

                    if (! $dryRun) {
                        // The extracted text lives on the profile; the upload row
                        // exists only to hold the file, so remove it too.
                        $document->delete();
                    }

                    $files++;
                    $sources++;
                }
            });

        // 2. Export files past the window. The row is a cache of a render that
        //    can be reproduced from the document, so the whole row goes.
        DocumentExport::query()
            ->where('created_at', '<', $cutoff)
            ->chunkById(200, function ($exportsChunk) use (&$files, &$exports, $dryRun) {
                foreach ($exportsChunk as $export) {
                    if (! $dryRun && Storage::disk('local')->exists($export->storage_path)) {
                        Storage::disk('local')->delete($export->storage_path);
                    }

                    if (! $dryRun) {
                        $export->delete();
                    }

                    $files++;
                    $exports++;
                }
            });

        // 3. Failed job payloads contain serialised models, so they are pruned too.
        $failedJobs = DB::table('failed_jobs')->where('failed_at', '<', $cutoff)->count();

        if (! $dryRun && $failedJobs > 0) {
            DB::table('failed_jobs')->where('failed_at', '<', $cutoff)->delete();
        }

        $prefix = $dryRun ? '[dry-run] would remove' : 'removed';
        $this->info("{$prefix} {$files} file(s): {$sources} source CV(s), {$exports} export(s); {$failedJobs} failed job(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
