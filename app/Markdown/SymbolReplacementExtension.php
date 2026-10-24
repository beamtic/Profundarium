<?php

namespace App\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

final class SymbolReplacementExtension implements ExtensionInterface, InlineParserInterface
{
    private const SYMBOLS = [
        '(c)' => '©',
        '(r)' => '®',
        '(tm)' => '™',
        '(p)' => '§',
        '+-' => '±',
    ];

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addInlineParser($this);
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('\\([cCrRpP]\\)|\\([tT][mM]\\)|\\+-');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $match = $inlineContext->getFullMatch();
        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
        $inlineContext->getContainer()->appendChild(new Text(self::SYMBOLS[strtolower($match)]));

        return true;
    }
}
