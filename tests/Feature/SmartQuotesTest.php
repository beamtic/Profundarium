<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmartQuotesTest extends TestCase
{
    public function test_double_and_single_quotes_and_apostrophes(): void
    {
        $response = $this->renderNote(<<<'MD'
"Smartypants, double quotes"

'Smartypants, single quotes'

"She said 'hello'." Don't forget Jacob's notes.
MD);
        $response->assertSee('“Smartypants, double quotes”', false);
        $response->assertSee('‘Smartypants, single quotes’', false);
        $response->assertSee('“She said ‘hello’.” Don’t forget Jacob’s notes.', false);
    }

    public function test_quotes_across_inline_formatting_and_inside_alerts(): void
    {
        $response = $this->renderNote(<<<'MD'
:::info
"A **bold** word" and 'an *italic* word'.
:::

["A link"](https://example.test/ "Straight title")
MD);
        $response->assertSee('“A <strong>bold</strong> word” and ‘an <em>italic</em> word’.', false);
        $response->assertSee('title="Straight title">“A link”</a>', false);
    }

    public function test_code_tex_escaped_quotes_and_html_attributes_are_preserved(): void
    {
        $response = $this->renderNote(<<<'MD'
`"straight" 'single' don't`

```text
"straight" 'single' don't
```

    "indented" 'single'

\"escaped\" \'single\'

$\text{It's "math"}$

<span title="It's a &quot;title&quot;">HTML</span>
MD);
        foreach (['“', '”', '‘', '’'] as $quote) {
            $response->assertDontSee($quote, false);
        }
        // DOM decoding avoids depending on entity spelling in serialized HTML.
        $document = \Dom\HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR, 'UTF-8');
        $this->assertSame('"straight" \'single\' don\'t', $document->getElementsByTagName('code')[0]->textContent);
        $this->assertStringContainsString('"escaped" \'single\'', $document->body->textContent);
        $this->assertStringContainsString('\\(\\text{It\'s "math"}\\)', $document->body->textContent);
        $this->assertSame('It\'s a "title"', $document->getElementsByTagName('span')[1]->getAttribute('title'));
    }

    private function renderNote(string $markdown): \Illuminate\Testing\TestResponse
    {
        config(['services.hedgedoc.url' => 'https://notes.example.test']);
        Http::fake(['https://notes.example.test/quotes/download' => Http::response($markdown)]);

        return $this->get('/profundarium/quotes')->assertOk();
    }
}
