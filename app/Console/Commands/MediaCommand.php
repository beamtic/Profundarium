<?php

namespace App\Console\Commands;

use App\Media\MediaScan;
use Illuminate\Console\Command;

abstract class MediaCommand extends Command
{
    protected function display(MediaScan $scan): void
    {
        $this->line('Notes scanned: ' . $scan->notes);
        $this->line('Images scanned: ' . $scan->images);
        $this->line('Unused images: ' . count($scan->unused));
        $this->line('Reclaimable: ' . $this->formatBytes($scan->bytes()));
        foreach (array_keys($scan->unused) as $path) {
            $this->line($scan->url($path));
        }
    }

    private function formatBytes(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return number_format($bytes, $unit === 'B' ? 0 : 1) . ' ' . $unit;
            }

            $bytes = $bytes / 1024;
        }

        return number_format($bytes, 1) . ' TB';
    }
}
