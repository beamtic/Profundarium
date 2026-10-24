<?php

namespace App\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Node;
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

final class AlertExtension implements ExtensionInterface, BlockStartParserInterface, NodeRendererInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addBlockStartParser($this, 50);
        $environment->addRenderer(AlertBlock::class, $this);
    }

    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented() || ! preg_match('/^ {0,3}(:{3,})\s*(success|info|warning|danger)\s*$/', $cursor->getRemainder(), $match)) {
            return BlockStart::none();
        }

        $cursor->advanceToEnd();

        return BlockStart::of(new AlertParser($match[2], strlen($match[1])))->at($cursor);
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        AlertBlock::assertInstanceOf($node);

        return new HtmlElement('div', ['class' => 'alert alert-' . $node->type], "\n" . $childRenderer->renderNodes($node->children()) . "\n");
    }
}

final class AlertBlock extends AbstractBlock
{
    public function __construct(public readonly string $type)
    {
        parent::__construct();
    }
}

final class AlertParser extends AbstractBlockContinueParser
{
    private AlertBlock $block;

    public function __construct(string $type, private readonly int $fenceLength)
    {
        $this->block = new AlertBlock($type);
    }

    public function getBlock(): AlertBlock
    {
        return $this->block;
    }

    public function isContainer(): bool
    {
        return true;
    }

    public function canContain(AbstractBlock $childBlock): bool
    {
        return true;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        if (! $cursor->isIndented() && preg_match('/^ {0,3}:{' . $this->fenceLength . ',}\s*$/', $cursor->getRemainder())) {
            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }
}
