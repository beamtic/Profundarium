<?php

namespace App\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

final class DiagramExtension implements ExtensionInterface, NodeRendererInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addRenderer(FencedCode::class, $this, 20);
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?\Stringable
    {
        FencedCode::assertInstanceOf($node);
        $type = $node->getInfoWords()[0] ?? '';
        if (! in_array($type, ['sequence', 'flow', 'graphviz', 'mermaid', 'abc'], true)) {
            return null;
        }
        $source = new HtmlElement('pre', ['class' => 'diagram-source'], new HtmlElement('code', [], htmlspecialchars($node->getLiteral(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));

        return new HtmlElement('figure', ['class' => 'diagram', 'data-diagram' => $type], $source);
    }
}
