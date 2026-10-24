<?php

namespace App\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Renderer\Block\FencedCodeRenderer;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

final class PygmentsExtension implements ExtensionInterface, NodeRendererInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addRenderer(FencedCode::class, $this, 10);
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        FencedCode::assertInstanceOf($node);
        $language = $node->getInfoWords()[0] ?? '';
        $code = $node->getLiteral();
        if (config('highlighting.enabled') && strlen($code) <= 100000
            && preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_+.-]{0,63}$/D', $language)) {
            // An argument array bypasses the shell entirely. Code goes through
            // stdin, never an argument or filename. No custom lexer loading (-x).
            $process = new Process([
                config('highlighting.binary'), '-l', $language, '-f', 'html',
                '-O', 'nowrap=True,encoding=utf-8,stripnl=False,ensurenl=False',
            ], null, null, $code, 2);
            try {
                $process->run();
                if ($process->isSuccessful() && $process->getOutput() !== '') {
                    return new HtmlElement('pre', ['class' => 'pygments'], new HtmlElement('code', [
                        'class' => 'language-' . $language,
                    ], $process->getOutput()));
                }
            } catch (ExceptionInterface $exception) {
                // Missing executable or timeout must not break the note.
            }
        }

        return (new FencedCodeRenderer())->render($node, $childRenderer);
    }
}
