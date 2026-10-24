<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiagramsTest extends TestCase
{
    public function test_diagram_sources_are_preserved_and_use_only_a_local_loader(): void
    {
        $markdown = '';
        foreach (['sequence', 'flow', 'graphviz', 'mermaid', 'abc'] as $type) {
            $markdown .= "```{$type}\n<x> & \"quote\" :smile: --\n```\n\n";
        }
        $response = $this->renderNote($markdown);
        $document = \Dom\HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR, 'UTF-8');
        $this->assertCount(5, $document->querySelectorAll('figure[data-diagram]'));
        foreach ($document->querySelectorAll('.diagram-source') as $source) {
            $this->assertSame("<x> & \"quote\" :smile: --\n", $source->textContent);
        }
        $response->assertSee('src="/profundarium/assets/js/diagrams.js"', false);
        $response->assertDontSee('class="pygments"', false);
        $response->assertDontSee('cdn.jsdelivr.net', false);
    }

    public function test_inline_examples_and_ordinary_code_do_not_load_diagram_libraries(): void
    {
        $response = $this->renderNote("`mermaid`\n\n```text\ngraph LR\n```\n\n````markdown\n```mermaid\ngraph LR\n```\n````");
        $response->assertDontSee('data-diagram=', false);
        $response->assertDontSee('src="/profundarium/assets/js/diagrams.js"', false);
    }

    private function renderNote(string $markdown): \Illuminate\Testing\TestResponse
    {
        config(['services.hedgedoc.url' => 'https://notes.example.test']);
        Http::fake(['https://notes.example.test/diagrams/download' => Http::response($markdown)]);
        return $this->get('/profundarium/diagrams')->assertOk();
    }
}
