<?php

namespace App\Markdown;

use Dom\HTMLDocument;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final class NoteHtmlSanitizer
{
    // HedgeDoc's js-xss allowlist, including this fork's media additions.
    private const ELEMENTS = [
        'a' => ['target', 'href', 'title', 'rel'],
        'abbr' => ['title'], 'address' => [],
        'area' => ['shape', 'coords', 'href', 'alt'],
        'article' => [], 'aside' => [],
        'audio' => ['autoplay', 'controls', 'crossorigin', 'loop', 'muted', 'preload', 'src'],
        'b' => [], 'bdi' => ['dir'], 'bdo' => ['dir'], 'big' => [],
        'blockquote' => ['cite'], 'br' => [], 'caption' => [], 'center' => [],
        'cite' => [], 'code' => [],
        'col' => ['align', 'valign', 'span', 'width'],
        'colgroup' => ['align', 'valign', 'span', 'width'],
        'dd' => [], 'del' => ['datetime'], 'details' => ['open'],
        'div' => [], 'dl' => [], 'dt' => [], 'em' => [],
        'figcaption' => [], 'figure' => [], 'font' => ['color', 'size', 'face'],
        'footer' => [], 'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'h5' => [], 'h6' => [], 'header' => [], 'hr' => [], 'i' => [],
        'iframe' => ['allowfullscreen', 'name', 'referrerpolicy', 'src', 'width', 'height', 'title', 'loading', 'allow', 'srcdoc'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
        'ins' => ['datetime'], 'kbd' => [], 'li' => ['value'], 'mark' => [],
        'nav' => [], 'ol' => ['start'], 'p' => [], 'pre' => [],
        'rp' => [], 'rt' => [], 'ruby' => [], 's' => [], 'section' => [],
        'small' => [], 'span' => [], 'sub' => [], 'summary' => [], 'sup' => [],
        'strong' => [], 'strike' => [],
        'table' => ['width', 'border', 'align', 'valign'],
        'tbody' => ['align', 'valign'],
        'td' => ['width', 'rowspan', 'colspan', 'align', 'valign'],
        'tfoot' => ['align', 'valign'],
        'th' => ['width', 'rowspan', 'colspan', 'align', 'valign'],
        'thead' => ['align', 'valign'], 'tr' => ['rowspan', 'align', 'valign'],
        'tt' => [], 'u' => [], 'ul' => [],
        'video' => ['autoplay', 'controls', 'loop', 'muted', 'poster', 'preload', 'src', 'width', 'height', 'playsinline'],
        'source' => ['src', 'type', 'media', 'sizes'],
        'track' => ['default', 'kind', 'label', 'src', 'srclang'],
        // CommonMark task lists generate disabled checkboxes.
        'input' => ['type', 'checked', 'disabled'],
    ];

    public function sanitize(string $html): string
    {
        $document = HTMLDocument::createFromString(
            '<!DOCTYPE html><html><head></head><body>' . $html . '</body></html>',
            LIBXML_NOERROR,
            'UTF-8',
        );
        $config = (new HtmlSanitizerConfig())
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->allowLinkSchemes([
                'http', 'https', 'bitcoin', 'ftp', 'ftps', 'geo', 'im', 'irc', 'ircs',
                'magnet', 'mailto', 'matrix', 'mms', 'news', 'nntp', 'openpgp4fpr',
                'sftp', 'sip', 'sms', 'smsto', 'ssh', 'tel', 'urn', 'webcal', 'wtai', 'xmpp',
            ])
            ->allowMediaSchemes(['http', 'https', 'data'])
            ->withAttributeSanitizer(new NoteUrlSanitizer())
            // Sanitize the entire note rather than silently truncate it.
            ->withMaxInputLength(-1);

        foreach (self::ELEMENTS as $element => $attributes) {
            $config = $config->allowElement($element, array_merge($attributes, [
                'id', 'class', 'style', 'title', 'role', 'dir', 'lang',
                'aria-label', 'aria-hidden', 'aria-describedby',
            ]));
        }
        $config = $config->forceAttribute('iframe', 'sandbox', 'allow-scripts')
            ->forceAttribute('input', 'type', 'checkbox')
            ->forceAttribute('input', 'disabled', 'disabled');

        foreach ($document->getElementsByTagName('*') as $element) {
            foreach ($element->attributes as $attribute) {
                if (preg_match('/^data-[a-z0-9_.:-]+$/D', $attribute->name)) {
                    $config = $config->allowAttribute($attribute->name, array_keys(self::ELEMENTS));
                }
                if ($element->localName === 'a' && $attribute->name === 'href'
                    && preg_match('/^(web\+[a-z]+):/i', $attribute->value, $match)) {
                    $config = $config->allowLinkSchemes(array_merge($config->getAllowedLinkSchemes(), [strtolower($match[1])]));
                }
            }
            if ($element->localName === 'iframe' && $element->hasAttribute('srcdoc')) {
                $srcdoc = $element->getAttribute('srcdoc');
                if (!preg_match('~https://gist\.github\.com/([a-zA-Z0-9][a-zA-Z0-9-]{0,38}/[a-fA-F0-9]{1,64})\.js~', $srcdoc, $match)
                    || $srcdoc !== DocumentEmbedExtension::gistDocument($match[1])) {
                    $element->removeAttribute('srcdoc');
                }
            }
        }

        // Symfony treats style as head-only. Keep parsed HTML style nodes in
        // their original positions, with a separate attribute allowlist.
        $styles = [];
        foreach (iterator_to_array($document->getElementsByTagName('style')) as $style) {
            if ($style->namespaceURI !== 'http://www.w3.org/1999/xhtml') {
                continue;
            }
            foreach (iterator_to_array($style->attributes) as $attribute) {
                if (!in_array($attribute->name, ['id', 'class', 'media', 'type', 'title'], true)) {
                    $style->removeAttribute($attribute->name);
                }
            }
            $token = 'profundarium-style-' . bin2hex(random_bytes(24));
            $styles[$token] = $document->saveHtml($style);
            $style->replaceWith($document->createTextNode($token));
        }

        $fragment = '';
        foreach ($document->body->childNodes as $node) {
            $fragment .= $document->saveHtml($node);
        }

        return strtr((new HtmlSanitizer($config))->sanitize($fragment), $styles);
    }
}
