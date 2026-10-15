<?php

namespace App\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

final class EmDashExtension implements ExtensionInterface, InlineParserInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addInlineParser($this);
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('(?<!-)-{2,3}(?!-)');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
        $inlineContext->getContainer()->appendChild(new Text('—'));

        return true;
    }
}
