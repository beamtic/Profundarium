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

final class VideoEmbedExtension implements ExtensionInterface, InlineParserInterface, NodeRendererInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addInlineParser($this, 200);
        $environment->addRenderer(VideoEmbed::class, $this);
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::string('{%');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();
        if (! preg_match('/^\{%[ \t]*(youtube|vimeo)[ \t]+([A-Za-z0-9_-]+)[ \t]*%\}/', $cursor->getRemainder(), $match)) {
            return false;
        }
        if (($match[1] === 'youtube' && ! preg_match('/^[A-Za-z0-9_-]{11}$/', $match[2]))
            || ($match[1] === 'vimeo' && ! preg_match('/^[1-9][0-9]*$/', $match[2]))) {
            return false;
        }
        $inlineContext->getContainer()->appendChild(new VideoEmbed($match[2], $match[1]));
        $cursor->advanceBy(strlen($match[0]));

        return true;
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        VideoEmbed::assertInstanceOf($node);
        $id = $node->getLiteral();
        $youtube = $node->provider === 'youtube';
        $name = $youtube ? 'YouTube' : 'Vimeo';
        $url = $youtube ? 'https://www.youtube.com/watch?v=' . $id : 'https://vimeo.com/' . $id;
        $player = new HtmlElement('iframe', [
            'src' => $youtube ? 'https://www.youtube-nocookie.com/embed/' . $id : 'https://player.vimeo.com/video/' . $id,
            'title' => $name . ' video ' . $id,
            'loading' => 'lazy',
            'allow' => 'fullscreen; picture-in-picture; encrypted-media',
            'allowfullscreen' => 'allowfullscreen',
        ], '');
        $link = new HtmlElement('a', ['class' => 'video-link', 'href' => $url], 'Watch on ' . $name . ': ' . $id);

        // A span remains valid inside Markdown paragraphs and other inline markup.
        return new HtmlElement('span', ['class' => 'video-embed'], $player . $link);
    }
}

final class VideoEmbed extends AbstractStringContainer
{
    public function __construct(string $id, public readonly string $provider)
    {
        parent::__construct($id);
    }
}
