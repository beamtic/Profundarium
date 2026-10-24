<?php

namespace App\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Inline\AbstractStringContainer;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use League\CommonMark\Parser\MarkdownParserStateInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

final class MathExtension implements ExtensionInterface, InlineParserInterface, BlockStartParserInterface, NodeRendererInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addInlineParser($this, 200);
        $environment->addBlockStartParser($this, 50);
        $environment->addRenderer(MathInline::class, $this);
        $environment->addRenderer(MathBlock::class, $this);
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::string('$');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();
        // Avoid interpreting whitespace-delimited dollar amounts as inline math.
        // Consume TeX as one node so Markdown never interprets its contents.
        $pattern = <<<'REGEX'
~^(?:\$\$(?<display>(?:\\.|(?!\$\$)[^\\])+?)\$\$|\$(?![$\s])(?<inline>(?:\\.|[^$\\])+?)(?<!\s)\$(?!\d))~s
REGEX;
        if (! preg_match($pattern, $cursor->getRemainder(), $match, PREG_UNMATCHED_AS_NULL)) {
            return false;
        }

        $node = new MathInline($match['display'] ?? $match['inline']);
        $node->data->set('display', $match['display'] !== null);
        $inlineContext->getContainer()->appendChild($node);
        $cursor->advanceBy(mb_strlen($match[0], 'UTF-8'));

        return true;
    }

    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented() || ! preg_match('/^ {0,3}\$\$\s*$/', $cursor->getRemainder())) {
            return BlockStart::none();
        }
        $cursor->advanceToEnd();

        return BlockStart::of(new MathBlockParser())->at($cursor);
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        $display = $node instanceof MathBlock || $node->data->get('display', false);
        $tex = $node instanceof MathBlock ? $node->tex : $node->getLiteral();
        $delimited = $display ? '\\[' . $tex . '\\]' : '\\(' . $tex . '\\)';

        return new HtmlElement($node instanceof MathBlock ? 'div' : 'span', [
            'class' => $display ? 'mathjax-process math-display' : 'mathjax-process math-inline',
        ], htmlspecialchars($delimited, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
}

final class MathInline extends AbstractStringContainer
{
}

final class MathBlock extends AbstractBlock
{
    public string $tex = '';
}

final class MathBlockParser extends AbstractBlockContinueParser
{
    private MathBlock $block;
    private bool $openingLine = true;

    public function __construct()
    {
        $this->block = new MathBlock();
    }

    public function getBlock(): MathBlock
    {
        return $this->block;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        if (! $cursor->isIndented() && preg_match('/^ {0,3}\$\$\s*$/', $cursor->getRemainder())) {
            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }

    public function addLine(string $line): void
    {
        if ($this->openingLine) {
            $this->openingLine = false;
            return;
        }
        $this->block->tex .= $line . "\n";
    }
}
