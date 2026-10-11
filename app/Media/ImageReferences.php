<?php

namespace App\Media;

use App\Markdown\SizedImageExtension;
use Dom\HTMLDocument;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ImageReferences
{
    public function extract(string $markdown, string $baseUrl, ?string $noteId = null, ?string $noteUrl = null): array
    {
        if (! mb_check_encoding($markdown, 'UTF-8')) {
            throw new MediaException('A note is not valid UTF-8; scanning aborted.');
        }
        $html = Str::markdown($markdown, [
            'html_input' => 'allow',
            'allow_unsafe_links' => true,
            'disallowed_raw_html' => ['disallowed_tags' => []],
        ], [new SizedImageExtension()]);
        $document = HTMLDocument::createFromString(
            '<!DOCTYPE html><html><head></head><body>' . $html . '</body></html>',
            LIBXML_NOERROR,
            'UTF-8',
        );
        $references = [];
        foreach ($document->getElementsByTagName('*') as $element) {
            $urls = [];
            if (strtolower($element->tagName) === 'img') {
                $urls[] = $element->getAttribute('src') ?? '';
            }
            if ($element->hasAttribute('srcset')) {
                // Follow srcset's URL-token rule: commas inside URLs are legal.
                $remaining = $element->getAttribute('srcset');
                while (preg_match('/^[\s,]*([^\s]+)(.*)$/s', $remaining, $match)) {
                    $urls[] = rtrim($match[1], ',');
                    $remaining = str_ends_with($match[1], ',')
                        ? $match[2]
                        : (str_contains($match[2], ',') ? substr($match[2], strpos($match[2], ',') + 1) : '');
                }
            }
            foreach ($urls as $url) {
                $path = $this->normalize($url, $baseUrl, $noteId, $noteUrl);
                if ($path !== null) {
                    $references[$path] = true;
                }
            }
        }

        return $references;
    }

    public function normalize(string $url, string $baseUrl, ?string $noteId = null, ?string $noteUrl = null): ?string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '#')) {
            return null;
        }
        // Unfinished image placeholders cannot identify an uploaded file.
        if (in_array(strtolower($url), ['http://', 'https://'], true)) {
            return null;
        }
        $base = parse_url($baseUrl);
        $parts = parse_url(str_starts_with($url, '//') ? $base['scheme'] . ':' . $url : $url);
        if ($parts === false) {
            Log::warning('Media scan could not parse an image reference.', ['note_id' => $noteId, 'note_url' => $noteUrl, 'reference' => $url]);
            throw new MediaException('Cannot parse an image reference; cleanup aborted.');
        }
        if (isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }
        // Conservatively retain same-host references across HTTP/HTTPS or port changes.
        if (isset($parts['host']) && strcasecmp($parts['host'], $base['host']) !== 0) {
            return null;
        }
        $path = rawurldecode($parts['path'] ?? '');
        if (str_contains($path, "\0") || str_contains($path, '\\')) {
            throw new MediaException('Unsafe image reference; cleanup aborted.');
        }
        $prefix = rtrim(rawurldecode($base['path'] ?? ''), '/') . '/uploads/';
        // Relative uploads/... resolves under the configured HedgeDoc base path.
        if (! str_starts_with($path, '/') && ! isset($parts['host'])) {
            $path = rtrim(rawurldecode($base['path'] ?? ''), '/') . '/' . $path;
        }
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
            } else {
                $segments[] = $segment;
            }
        }
        $path = '/' . implode('/', $segments);

        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : null;
    }
}
