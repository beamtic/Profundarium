<?php

namespace App\Markdown;

use League\CommonMark\Delimiter\DelimiterInterface;
use League\CommonMark\Delimiter\Processor\DelimiterProcessorInterface;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Inline\AbstractInline;
use League\CommonMark\Node\Inline\AbstractStringContainer;
use League\CommonMark\Node\Inline\DelimitedInterface;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

final class TypographicExtension implements ExtensionInterface, InlineParserInterface, NodeRendererInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addInlineParser($this, 200);
        $environment->addDelimiterProcessor(new TypographicDelimiterProcessor('+', 'ins'));
        $environment->addDelimiterProcessor(new TypographicDelimiterProcessor('=', 'mark'));
        $environment->addRenderer(TypographicInline::class, $this);
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::oneOf('^', '~');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();
        $marker = $cursor->getCharacter();
        // Leave double tildes to CommonMark's existing strikethrough processor.
        if (str_starts_with($cursor->getRemainder(), '~~')) {
            return false;
        }

        $delimiter = preg_quote($marker, '/');
        $pattern = '/^' . $delimiter . '((?:\\\\[^\r\n]|[^' . $delimiter . '\\\\\s])+)' . $delimiter . '(?!' . $delimiter . ')/u';
        if (! preg_match($pattern, $cursor->getRemainder(), $match)) {
            // Invalid single tildes must remain text rather than becoming deletion.
            $inlineContext->getContainer()->appendChild(new Text($marker));
            $cursor->advanceBy(1);
            return true;
        }

        $node = new TypographicInline($marker === '^' ? 'sup' : 'sub', $marker);
        $node->appendChild(new Text(preg_replace('/\\\\([[:punct:]\s])/u', '$1', $match[1])));
        $inlineContext->getContainer()->appendChild($node);
        $cursor->advanceBy(mb_strlen($match[0], 'UTF-8'));

        return true;
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        TypographicInline::assertInstanceOf($node);

        return new HtmlElement($node->tag, [], $childRenderer->renderNodes($node->children()));
    }
}

final class TypographicInline extends AbstractInline implements DelimitedInterface
{
    public function __construct(public readonly string $tag, private readonly string $delimiter)
    {
        parent::__construct();
    }

    public function getOpeningDelimiter(): string
    {
        return $this->delimiter;
    }

    public function getClosingDelimiter(): string
    {
        return $this->delimiter;
    }
}

final class TypographicDelimiterProcessor implements DelimiterProcessorInterface
{
    public function __construct(private readonly string $marker, private readonly string $tag)
    {
    }

    public function getOpeningCharacter(): string
    {
        return $this->marker;
    }

    public function getClosingCharacter(): string
    {
        return $this->marker;
    }

    public function getMinLength(): int
    {
        return 2;
    }

    public function getDelimiterUse(DelimiterInterface $opener, DelimiterInterface $closer): int
    {
        return 2;
    }

    public function process(AbstractStringContainer $opener, AbstractStringContainer $closer, int $delimiterUse): void
    {
        $node = new TypographicInline($this->tag, str_repeat($this->marker, 2));
        $child = $opener->next();
        while ($child !== null && $child !== $closer) {
            $next = $child->next();
            $node->appendChild($child);
            $child = $next;
        }
        $opener->insertAfter($node);
    }
}
