<?php

namespace App\Console\Commands;

use App\Media\UnusedMedia;
use App\Media\MediaException;
use Throwable;

class MediaUnused extends MediaCommand
{
    protected $signature = 'media:unused';
    protected $description = 'List HedgeDoc uploads not referenced by any stored note';

    public function handle(UnusedMedia $media): int
    {
        $this->info('Scanning HedgeDoc notes...');
        try {
            $this->display($media->scan());
        } catch (Throwable $error) {
            if ($error instanceof MediaException) {
                $this->error($error->getMessage());
            }
            // Database/parser exceptions can embed private note contents or credentials.
            $this->error('Scan failed. Check PostgreSQL access, public URL, and uploads path/read permissions. No files were deleted.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
