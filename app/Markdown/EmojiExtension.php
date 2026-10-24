<?php

namespace App\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

final class EmojiExtension implements ExtensionInterface, InlineParserInterface
{
    private array $emoji;

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $this->emoji = json_decode(file_get_contents(resource_path('markdown/emoji.json')), true, 512, JSON_THROW_ON_ERROR);
        $environment->addInlineParser($this);
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex(':[a-zA-Z0-9_+\-]+:');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $match = $inlineContext->getFullMatch();
        $symbol = $this->emoji[substr($match, 1, -1)] ?? null;
        if ($symbol === null) {
            return false;
        }
        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
        $inlineContext->getContainer()->appendChild(new Text($symbol));

        return true;
    }
}
