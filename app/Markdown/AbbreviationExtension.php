<?php

namespace App\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Inline\AbstractStringContainer;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\Query;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

final class AbbreviationExtension implements ExtensionInterface, BlockStartParserInterface, NodeRendererInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addBlockStartParser($this, 60);
        $environment->addEventListener(DocumentParsedEvent::class, [$this, 'expand'], -10);
        $environment->addRenderer(Abbreviation::class, $this);
    }

    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented() || ! preg_match('/^ {0,3}\*\[([^\]\r\n]+)\]:[ \t]*(\S[^\r\n]*)$/u', $cursor->getRemainder(), $match)) {
            return BlockStart::none();
        }
        $cursor->advanceToEnd();

        return BlockStart::of(new AbbreviationDefinitionParser($match[1], trim($match[2])))->at($cursor);
    }

    public function expand(DocumentParsedEvent $event): void
    {
        $definitions = [];
        foreach ((new Query())->where(Query::type(AbbreviationDefinition::class))->findAll($event->getDocument()) as $definition) {
            $definitions[$definition->term] ??= $definition->description;
            $definition->detach();
        }
        if ($definitions === []) {
            return;
        }
        $terms = array_keys($definitions);
        usort($terms, fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $pattern = '/(?<![\pL\pN\pM_])(' . implode('|', array_map(fn (string $term): string => preg_quote($term, '/'), $terms)) . ')(?![\pL\pN\pM_])/u';
        foreach ((new Query())->where(Query::type(Text::class))->findAll($event->getDocument()) as $text) {
            // Image alternative text is an attribute, not an HTML text container.
            for ($parent = $text->parent(); $parent !== null; $parent = $parent->parent()) {
                if ($parent instanceof Image) {
                    continue 2;
                }
            }
            $parts = preg_split($pattern, $text->getLiteral(), -1, PREG_SPLIT_DELIM_CAPTURE);
            if (count($parts) === 1) {
                continue;
            }
            foreach ($parts as $index => $part) {
                if ($part === '') {
                    continue;
                }
                $text->insertBefore($index % 2 === 1
                    ? new Abbreviation($part, $definitions[$part])
                    : new Text($part));
            }
            $text->detach();
        }
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        Abbreviation::assertInstanceOf($node);

        return new HtmlElement('abbr', ['title' => $node->description], htmlspecialchars($node->getLiteral(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
}

final class Abbreviation extends AbstractStringContainer
{
    public function __construct(string $term, public readonly string $description)
    {
        parent::__construct($term);
    }
}

final class AbbreviationDefinition extends AbstractBlock
{
    public function __construct(public readonly string $term, public readonly string $description)
    {
        parent::__construct();
    }
}

final class AbbreviationDefinitionParser extends AbstractBlockContinueParser
{
    private AbbreviationDefinition $block;

    public function __construct(string $term, string $description)
    {
        $this->block = new AbbreviationDefinition($term, $description);
    }

    public function getBlock(): AbbreviationDefinition
    {
        return $this->block;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        return BlockContinue::none();
    }
}
