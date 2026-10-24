<?php

namespace App\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Inline\AbstractStringContainer;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

final class DocumentEmbedExtension implements ExtensionInterface, InlineParserInterface, NodeRendererInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addInlineParser($this, 200);
        $environment->addRenderer(DocumentEmbed::class, $this);
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::string('{%');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();
        if (! preg_match('/^\{%[ \t]*(gist|pdf)[ \t]+([^\s<>"\x27]+)[ \t]*%\}/u', $cursor->getRemainder(), $match)) {
            return false;
        }
        $target = $match[2];
        if ($match[1] === 'gist') {
            if (! preg_match('~^[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,38})/[a-fA-F0-9]{1,64}$~D', $target)) {
                return false;
            }
        } elseif (! $this->validPdfUrl($target)) {
            return false;
        }
        $inlineContext->getContainer()->appendChild(new DocumentEmbed($target, $match[1]));
        $cursor->advanceBy(mb_strlen($match[0], 'UTF-8'));

        return true;
    }

    private function validPdfUrl(string $url): bool
    {
        if (str_contains($url, '\\') || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return false;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }
        $parts = parse_url($url);

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && ! isset($parts['user']) && ! isset($parts['pass']);
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        DocumentEmbed::assertInstanceOf($node);
        $gist = $node->type === 'gist';
        $url = $gist ? 'https://gist.github.com/' . $node->getLiteral() : $node->getLiteral();
        $attributes = [
            'title' => $gist ? 'GitHub Gist ' . $node->getLiteral() : 'PDF document',
            'loading' => 'lazy',
            'referrerpolicy' => 'no-referrer',
        ];
        if ($gist) {
            // GitHub's document.write embed runs only in an opaque-origin frame,
            // never with access to Profundarium's DOM, storage, or navigation.
            $attributes['sandbox'] = 'allow-scripts';
            $policy = "default-src 'none'; script-src https://gist.github.com; style-src 'unsafe-inline' https://github.githubassets.com https://gist.github.com; img-src data: https://gist.github.com https://*.githubusercontent.com; base-uri 'none'";
            $attributes['srcdoc'] = '<!DOCTYPE html><html><head><meta charset="utf-8">'
                . '<meta name="referrer" content="no-referrer">'
                . '<meta http-equiv="Content-Security-Policy" content="' . htmlspecialchars($policy, ENT_QUOTES, 'UTF-8') . '">'
                . '<style>body{margin:0}.gist .gist-data{max-height:none}</style>'
                . '</head><body><script src="' . $url . '.js"></script></body></html>';
        } else {
            // Browser PDF viewers can be blocked by iframe sandboxing.
            $attributes['src'] = $url;
        }
        $frame = new HtmlElement('iframe', $attributes, '');
        $link = new HtmlElement('a', ['class' => 'document-link', 'href' => $url], $gist ? 'Open Gist' : 'Open PDF');

        return new HtmlElement('span', ['class' => 'document-embed document-embed-' . $node->type], $frame . $link);
    }
}

final class DocumentEmbed extends AbstractStringContainer
{
    public function __construct(string $target, public readonly string $type)
    {
        parent::__construct($target);
    }
}
