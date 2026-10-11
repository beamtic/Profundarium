<?php

use App\Markdown\SizedImageExtension;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;

Route::get('/profundarium/{note?}', function (Request $request, ?string $note = null) {
    $hedgedocUrl = rtrim(config('services.hedgedoc.url'), '/');

    if ($note === null) {
        $path = resource_path('notes/default.md');
        abort_unless(is_readable($path), 404);
        $markdown = file_get_contents($path);
    } else {
        $response = Http::get("{$hedgedocUrl}/{$note}/download");

        $contentType = strtolower((string) $response->header('Content-Type'));
        $isForbidden = in_array($response->status(), [401, 403], true)
            || str_contains($contentType, 'text/html');

        if ($isForbidden) {
            $path = resource_path('notes/not-found.md');
            abort_unless(is_readable($path), 404);
            $markdown = file_get_contents($path);
            $status = 404;
        } else {
            abort_unless($response->successful(), 404);
            $markdown = $response->body();
            $status = 200;
        }
    }

    $robots = null;
    if (preg_match('/\A---\r?\n(.*?)\r?\n---(?:\r?\n|$)/s', $markdown, $frontMatter)) {
        foreach (preg_split('/\r?\n/', $frontMatter[1]) as $line) {
            if (preg_match('/^\s*robots\s*:\s*(.*?)\s*$/i', $line, $match)) {
                $robots = trim($match[1], " \t\"'");
                break;
            }
        }

        $markdown = substr($markdown, strlen($frontMatter[0]));
    }

    $html = Str::markdown($markdown, [
            'html_input' => 'allow',
            'heading_permalink' => [
                'apply_id_to_heading' => true,
                'fragment_prefix' => '',
                'id_prefix' => '',
                'insert' => 'none',
            ],
            'disallowed_raw_html' => [
                'disallowed_tags' => [
                    'title',
                    'textarea',
                    'xmp',
                    'iframe',
                    'noembed',
                    'noframes',
                    'script',
                    'plaintext',
                ],
            ],
        ], [new HeadingPermalinkExtension(), new SizedImageExtension()]);

    $headings = [];
    $html = preg_replace_callback(
        '/<h([1-6]) id="([^"]+)">(.*?)<\/h\1>/s',
        function (array $match) use (&$headings): string {
            $headings[] = [
                'level' => (int) $match[1],
                'id' => $match[2],
                'label' => strip_tags($match[3]),
            ];

            return $match[0];
        },
        $html,
    );

    if ($headings !== []) {
        // Nest under the preceding shallower heading, even when levels are skipped.
        $renderList = function (int &$index, int $parentLevel = 0) use (&$renderList, $headings): string {
            $list = '<ul>';

            while ($index < count($headings) && $headings[$index]['level'] > $parentLevel) {
                $heading = $headings[$index++];
                $list .= sprintf(
                    '<li class="toc-level-%d"><a href="#%s">%s</a>',
                    $heading['level'],
                    e($heading['id']),
                    e($heading['label']),
                );

                if ($index < count($headings) && $headings[$index]['level'] > $heading['level']) {
                    $list .= $renderList($index, $heading['level']);
                }

                $list .= '</li>';
            }

            return $list . '</ul>';
        };

        $index = 0;
        $toc = '<nav class="toc" aria-label="Table of contents">'
            . $renderList($index)
            . '</nav>';
        $html = str_replace('<p>[TOC]</p>', $toc, $html);
    }

    // Rewrite actual anchor URLs, including links written as raw HTML in Markdown.
    // Leave other sites, images, code examples, and TOC fragments unchanged.
    // Explicit body keeps leading <style> blocks in the note fragment.
    $document = \Dom\HTMLDocument::createFromString(
        '<!DOCTYPE html><html><head></head><body>' . $html . '</body></html>',
        LIBXML_NOERROR,
        'UTF-8',
    );
    // Recognize the public request host as well as the internal fetch URL.
    $linkSources = [$hedgedocUrl, $request->getSchemeAndHttpHost()];
    $sourceUrlPattern = '(?:' . implode('|', array_map(
        fn (string $url): string => preg_quote(preg_replace('~^https?:~i', '', rtrim($url, '/')), '~'),
        array_unique($linkSources),
    )) . ')';
    $noteLinkPattern = '~^(?:(?:(?:https?:)?' . $sourceUrlPattern . ')?/?s/([A-Za-z0-9_-]+)'
        . '|(?:https?:)?' . $sourceUrlPattern . '/([A-Za-z0-9_-]+))([?#].*)?$~i';
    foreach ($document->getElementsByTagName('a') as $link) {
        $href = $link->getAttribute('href');
        if (preg_match($noteLinkPattern, $href, $match)) {
            $noteId = $match[1] !== '' ? $match[1] : $match[2];
            $link->setAttribute('href', '/profundarium/' . $noteId . ($match[3] ?? ''));
        }
    }

    $html = '';
    foreach ($document->body->childNodes as $node) {
        $html .= $document->saveHtml($node);
    }

    $response = response()->view('note', [
        'html' => $html,
        'title' => trim($document->getElementsByTagName('h1')->item(0)?->textContent ?? ''),
    ], $status ?? 200);

    $response->header('X-Robots-Tag', $robots ?? 'noindex, nofollow');

    return $response;
});
