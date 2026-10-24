<?php

namespace App\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\SmartPunct\QuoteParser;
use League\CommonMark\Extension\SmartPunct\Quote;
use League\CommonMark\Extension\SmartPunct\QuoteProcessor;
use League\CommonMark\Extension\SmartPunct\ReplaceUnpairedQuotesListener;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Query;

final class SmartQuotesExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        // Use CommonMark's quote handling without changing dashes or ellipses.
        $environment->addInlineParser(new QuoteParser(), 10);
        $environment->addDelimiterProcessor(QuoteProcessor::createDoubleQuoteProcessor());
        $environment->addDelimiterProcessor(QuoteProcessor::createSingleQuoteProcessor());
        $environment->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event): void {
            // Link labels can clear their delimiter stack before quotes pair.
            // Recover closing double quotes from the preceding text before the
            // standard listener turns all remaining double quotes into openers.
            foreach ((new Query())->where(Query::type(Quote::class))->findAll($event->getDocument()) as $quote) {
                $previous = $quote->previous();
                if ($quote->getLiteral() === Quote::DOUBLE_QUOTE && $previous instanceof Text
                    && preg_match('/[\pL\pN\pM.!?,;:)\]}]$/u', $previous->getLiteral())) {
                    $quote->setLiteral(Quote::DOUBLE_QUOTE_CLOSER);
                }
            }
        }, 10);
        $environment->addEventListener(DocumentParsedEvent::class, new ReplaceUnpairedQuotesListener());
    }
}
