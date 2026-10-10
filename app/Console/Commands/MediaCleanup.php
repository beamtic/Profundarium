<?php

namespace App\Console\Commands;

use App\Media\UnusedMedia;
use App\Media\MediaException;
use Throwable;

class MediaCleanup extends MediaCommand
{
    protected $signature = 'media:cleanup {--force : Explicitly authorize permanent deletion without prompting}';
    protected $description = 'Permanently delete unreferenced HedgeDoc image uploads after confirmation';

    public function handle(UnusedMedia $media): int
    {
        try {
            $scan = $media->scan();
            $this->display($scan);
            if ($scan->unused === []) {
                return self::SUCCESS;
            }
            if (! $this->option('force')) {
                if (! $this->input->isInteractive()) {
                    $this->error('Non-interactive deletion requires --force. No files were deleted.');

                    return self::FAILURE;
                }
                if (! $this->confirm('Permanently delete these images?', false)) {
                    $this->info('Cancelled. No files were deleted.');

                    return self::SUCCESS;
                }
            }
            $failed = 0;
            $deleted = 0;
            foreach ($media->delete($scan) as $path => $success) {
                if ($success) {
                    $this->line('Deleted: ' . $scan->url($path));
                    $deleted++;
                } else {
                    $this->error('Failed or skipped (changed/referenced file): ' . $scan->url($path));
                    $failed++;
                }
            }
            $this->info("Deleted: {$deleted}; failed or skipped: {$failed}.");

            return $failed === 0 ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $error) {
            if ($error instanceof MediaException) {
                $this->error($error->getMessage());
            }
            $this->error('Cleanup aborted. Check PostgreSQL access, reference parsing, and uploads permissions/symbolic links. If deletion started, some files may already have been deleted.');

            return self::FAILURE;
        }
    }
}
