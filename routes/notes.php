<?php

use App\Markdown\SizedImageExtension;
use App\Markdown\EmDashExtension;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

Route::get('/profundarium/{note?}', function (Request $request, ?string $note = null) {
    $hedgedocUrl = rtrim(config('services.hedgedoc.url'), '/');
    $lastModified = null;

    $readModificationTime = function (mixed $updatedAt): ?int {
        if (!is_string($updatedAt) || !preg_match('/^\d{4}-\d{2}-\d{2}T/', $updatedAt)) {
            return null;
        }

        try {
            $date = new \DateTimeImmutable($updatedAt);
        } catch (\DateMalformedStringException $exception) {
            return null;
        }

        $errors = \DateTimeImmutable::getLastErrors();
        if ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0)) {
            return null;
        }

        // HTTP modification dates must not be later than the response date.
        return min($date->getTimestamp(), time());
    };

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

            // A named alias is shared by the published and editor URLs. Without
            // an alias, those URLs use different IDs (short ID and encoded UUID).
            try {
                $published = Http::withoutRedirecting()->connectTimeout(2)->timeout(3)
                    ->get("{$hedgedocUrl}/{$note}/publish");
                $publishedPath = parse_url((string) $published->header('Location'), PHP_URL_PATH);
                if ($published->redirect() && is_string($publishedPath)
                    && preg_match('~/s/([^/]+)$~', $publishedPath, $match)) {
                    $candidate = rawurldecode($match[1]);
                    if ($candidate !== $note) {
                        $editor = Http::withoutRedirecting()->connectTimeout(2)->timeout(3)
                            ->get($hedgedocUrl . '/s/' . rawurlencode($candidate) . '/edit');
                        $editorPath = parse_url((string) $editor->header('Location'), PHP_URL_PATH);
                        if ($editor->redirect() && is_string($editorPath)
                            && rawurldecode(basename($editorPath)) === $candidate) {
                            $target = '/profundarium/' . rawurlencode($candidate);
                            $query = $request->getQueryString();

                            return redirect($target . ($query !== null ? '?' . $query : ''), 302);
                        }
                    }
                }
            } catch (ConnectionException $exception) {
                // Alias discovery is optional, just like modification metadata.
            }

            // Metadata is optional: an unavailable info endpoint must not hide the note.
            try {
                $info = Http::acceptJson()->connectTimeout(2)->timeout(3)
                    ->get("{$hedgedocUrl}/{$note}/info");
                $lastModified = $readModificationTime($info->successful() ? $info->json('updatetime') : null);
            } catch (ConnectionException $exception) {
                // Serve the content without a modification date when metadata cannot be used.
            }
        }
    }

    $robots = null;
    $openGraph = [];
    if (preg_match('/\A---\r?\n(.*?)\r?\n---(?:\r?\n|$)/s', $markdown, $frontMatter)) {
        try {
            $metadata = Yaml::parse($frontMatter[1]);
        } catch (ParseException $exception) {
            // Invalid optional metadata must not prevent the note from rendering.
            $metadata = [];
        }

        if (is_array($metadata)) {
            if (is_string($metadata['robots'] ?? null)) {
                $robots = $metadata['robots'];
            }

            if (is_array($metadata['opengraph'] ?? null)) {
                foreach ($metadata['opengraph'] as $property => $values) {
                    if (!is_string($property) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_:]*$/D', $property)) {
                        continue;
                    }

                    // Lists allow repeated properties, such as multiple preview images.
                    foreach (is_array($values) && array_is_list($values) ? $values : [$values] as $value) {
                        if (is_string($value) || is_int($value) || is_float($value)) {
                            $openGraph[] = ['property' => 'og:' . $property, 'content' => (string) $value];
                        }
                    }
                }
            }
        }

        $markdown = substr($markdown, strlen($frontMatter[0]));
    }

    if ($request->isMethod('HEAD')) {
        $response = response('', $status ?? 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Robots-Tag' => $robots ?? 'noindex, nofollow',
        ]);
        if ($lastModified !== null) {
            $response->setLastModified(new \DateTimeImmutable('@' . $lastModified));
        }

        return $response;
    }

    $html = Str::markdown($markdown, [
            'html_input' => 'allow',
            'renderer' => [
                'soft_break' => "<br>\n",
            ],
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
        ], [new HeadingPermalinkExtension(), new SizedImageExtension(), new EmDashExtension()]);

    $headings = [];
    $html = preg_replace_callback(
        '/<h([1-6]) id="([^"]+)">(.*?)<\/h\1>/s',
        function (array $match) use (&$headings): string {
            $headings[] = [
                'level' => (int) $match[1],
                'id' => $match[2],
                'label' => html_entity_decode(strip_tags($match[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
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
        'openGraph' => $openGraph,
        'title' => trim($document->getElementsByTagName('h1')->item(0)?->textContent ?? ''),
    ], $status ?? 200);

    $response->header('X-Robots-Tag', $robots ?? 'noindex, nofollow');

    if ($lastModified !== null) {
        $response->setLastModified(new \DateTimeImmutable('@' . $lastModified));
    }

    return $response;
});
