<?php

namespace App\Media;

use Illuminate\Filesystem\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class UnusedMedia
{
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif', 'bmp', 'ico', 'tif', 'tiff', 'apng'];

    public function __construct(
        private NoteReader $notes,
        private ImageReferences $references,
        private Filesystem $files,
    ) {}

    public function scan(): MediaScan
    {
        $configuredRoot = config('services.hedgedoc.uploads_path');
        $baseUrl = rtrim((string) config('services.hedgedoc.public_url'), '/');
        $base = parse_url($baseUrl);
        if (! is_array($base) || ! isset($base['host'], $base['scheme'])
            || ! in_array($base['scheme'], ['http', 'https'], true)
            || isset($base['user'])
            || isset($base['query']) || isset($base['fragment'])) {
            throw new MediaException('Set HEDGEDOC_PUBLIC_URL to the public HTTP(S) base URL.');
        }
        if (! is_string($configuredRoot) || $configuredRoot === '' || ! str_starts_with($configuredRoot, '/')) {
            throw new MediaException('Set HEDGEDOC_UPLOADS_PATH to an absolute uploads directory.');
        }
        $this->rejectLinks($configuredRoot);
        $root = realpath($configuredRoot);
        if ($root === false || $root === '/' || ! is_dir($root) || ! is_readable($root)) {
            throw new MediaException('Uploads directory is missing or unreadable.');
        }
        // Enumerate first so new uploads during the note snapshot are not candidates.
        $images = $this->images($root);
        $used = [];
        $count = $this->notes->each(function (string $content, ?string $noteId = null, ?string $shortid = null, ?string $alias = null) use (&$used, $baseUrl): void {
            $used += $this->references->extract($content, $baseUrl, $noteId,
                $shortid ? $baseUrl . '/s/' . rawurlencode($shortid)
                    : ($alias ? $baseUrl . '/' . rawurlencode($alias) : null));
        });

        return new MediaScan($root, $baseUrl, $count, count($images), array_diff_key($images, $used));
    }

    private function images(string $root): array
    {
        $images = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $entry) {
            $path = $entry->getPathname();
            if ($entry->isLink()) {
                throw new MediaException('Uploads directory contains a symbolic link; scanning aborted.');
            }
            if ($entry->isDir()) {
                if (! is_readable($path) || ! is_executable($path)) {
                    throw new MediaException('An uploads subdirectory is unreadable.');
                }
                continue;
            }
            if (! $entry->isFile()) {
                throw new MediaException('Uploads directory contains a non-regular file.');
            }
            if (in_array(strtolower($entry->getExtension()), self::EXTENSIONS, true)) {
                $relative = substr($path, strlen($root) + 1);
                $images[$relative] = $this->fingerprint($root, $relative);
            }
        }
        ksort($images);

        return $images;
    }

    private function rejectLinks(string $path): void
    {
        $current = '';
        foreach (explode('/', $path) as $segment) {
            if ($segment === '') {
                continue;
            }
            if ($segment === '.' || $segment === '..') {
                throw new MediaException('Uploads paths must not contain dot segments.');
            }
            $current .= '/' . $segment;
            if (is_link($current)) {
                throw new MediaException('Uploads paths must not contain symbolic links.');
            }
        }
    }

    private function fingerprint(string $root, string $relative): array
    {
        $path = $root . '/' . $relative;
        clearstatcache();
        $this->rejectLinks($path);
        $resolved = realpath($path);
        if ($resolved === false || ! str_starts_with($resolved, $root . '/')
            || ! is_file($resolved) || ! is_readable($resolved)) {
            throw new MediaException('An upload is missing, unreadable, or outside the uploads directory.');
        }
        $stat = lstat($path);
        $hash = hash_file('sha256', $path);
        if ($stat === false || $hash === false) {
            throw new MediaException('Cannot inspect an uploaded image.');
        }

        return [
            'size' => $this->files->size($path),
            'device' => $stat['dev'],
            'inode' => $stat['ino'],
            'mtime' => $stat['mtime'],
            'ctime' => $stat['ctime'],
            'hash' => $hash,
        ];
    }

    public function delete(MediaScan $confirmed): array
    {
        // Refresh references after the operator has reviewed the list.
        $fresh = $this->scan();
        if ($fresh->root !== $confirmed->root || $fresh->baseUrl !== $confirmed->baseUrl) {
            throw new MediaException('Media configuration changed; deletion aborted.');
        }
        $results = [];
        foreach ($confirmed->unused as $path => $fingerprint) {
            if (($fresh->unused[$path] ?? null) !== $fingerprint) {
                $results[$path] = false;
                continue;
            }
            if ($this->fingerprint($fresh->root, $path) !== $fingerprint) {
                $results[$path] = false;
                continue;
            }
            $results[$path] = $this->files->delete($fresh->root . '/' . $path);
            clearstatcache();
        }

        return $results;
    }
}
