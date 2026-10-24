<?php

namespace App\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Inline\AbstractStringContainer;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

final class BlockquoteTagsExtension implements ExtensionInterface, InlineParserInterface, NodeRendererInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addInlineParser($this, 200);
        $environment->addRenderer(BlockQuote::class, $this, 10);
        $environment->addRenderer(BlockquoteTag::class, $this);
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('\\[(?:name|time|color)=');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $quote = $inlineContext->getContainer();
        while ($quote !== null && ! $quote instanceof BlockQuote) {
            $quote = $quote->parent();
        }
        $cursor = $inlineContext->getCursor();
        if ($quote === null || ! preg_match('/^\[(name|time|color)=([^\]\r\n]+)\]/u', $cursor->getRemainder(), $match)) {
            return false;
        }
        $value = trim($match[2]);
        if ($value === '' || ($match[1] === 'color' && ! preg_match('/^(?:#[0-9a-f]{3}(?:[0-9a-f]{3})?|black|silver|gray|white|maroon|red|purple|fuchsia|green|lime|olive|yellow|navy|blue|teal|aqua|orange|rebeccapurple)$/iD', $value))) {
            return false;
        }
        $quote->data->set('blockquote_tags/' . $match[1], $value);
        $inlineContext->getContainer()->appendChild(new BlockquoteTag());
        $cursor->advanceBy(mb_strlen($match[0], 'UTF-8'));

        return true;
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable|string
    {
        if ($node instanceof BlockquoteTag) {
            return '';
        }
        BlockQuote::assertInstanceOf($node);
        $attributes = $node->data->get('attributes', []);
        $tags = $node->data->get('blockquote_tags', []);
        if (isset($tags['color'])) {
            $attributes['style'] = ($attributes['style'] ?? '') . ';border-left-color:' . $tags['color'];
        }
        $content = $childRenderer->renderNodes($node->children());
        $attribution = [];
        foreach (['name', 'time'] as $type) {
            if (isset($tags[$type])) {
                $attribution[] = new HtmlElement('span', ['class' => 'blockquote-' . $type], htmlspecialchars($tags[$type], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            }
        }
        if ($attribution !== []) {
            $content .= "\n" . new HtmlElement('footer', ['class' => 'blockquote-attribution'], implode(' · ', $attribution));
        }

        return new HtmlElement('blockquote', $attributes, "\n" . $content . "\n");
    }
}

final class BlockquoteTag extends AbstractStringContainer
{
}
