<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DefinitionListsAndEllipsesTest extends TestCase
{
    public function test_definition_lists_support_markup_multiple_definitions_and_continuation(): void
    {
        $html = $this->renderNote(<<<'MD'
Term **one**

:   First definition...
:   Second definition.

    Another paragraph with H~2~O.

Term two
:   A final definition.
MD);
        $document = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
        $this->assertCount(2, $document->getElementsByTagName('dt'));
        $this->assertCount(3, $document->getElementsByTagName('dd'));
        $this->assertStringContainsString('<dt>Term <strong>one</strong></dt>', $html);
        $this->assertStringContainsString('First definition…', $html);
        $this->assertStringContainsString('Another paragraph with H<sub>2</sub>O.', $html);
        $this->assertStringContainsString('Another paragraph', $document->getElementsByTagName('dd')[1]->textContent);
    }

    public function test_ellipses_use_commonmark_rules_without_changing_dashes(): void
    {
        $html = $this->renderNote('Wait... . . . .. .... ..... -- --- "quoted..."');
        $this->assertStringContainsString('<p>Wait… … .. …. ….. — — “quoted…”</p>', $html);
    }

    public function test_code_tex_attributes_links_and_escaped_dots_are_preserved(): void
    {
        $html = $this->renderNote(<<<'MD'
`... . . .`

```markdown
Term
:   Definition...
```

    Term
    :   Definition...

$... + \ldots$ and \...

[Wait...](https://example.test/a... "Title...")

<span title="...">HTML</span>
MD);
        $this->assertStringNotContainsString('<dl>', $html);
        $this->assertStringContainsString('<code>... . . .</code>', $html);
        $this->assertStringContainsString('Definition...', $html);
        $this->assertStringContainsString('\\(... + \\ldots\\)</span> and ...', $html);
        $this->assertStringContainsString('href="https://example.test/a..." title="Title...">Wait…</a>', $html);
        $this->assertStringContainsString('<span title="...">HTML</span>', $html);
    }

    public function test_definition_lists_coexist_with_alerts_footnotes_and_abbreviations(): void
    {
        $html = $this->renderNote(<<<'MD'
:::info
HTML
:   A definition... with a reference[^note].
:::

[^note]: Another note...

*[HTML]: Hyper Text Markup Language
MD);
        $this->assertStringContainsString('<dt><abbr title="Hyper Text Markup Language">HTML</abbr></dt>', $html);
        $this->assertStringContainsString('A definition…', $html);
        $this->assertStringContainsString('Another note…', $html);
        $this->assertStringContainsString('class="footnote-ref"', $html);
    }

    private function renderNote(string $markdown): string
    {
        config(['services.hedgedoc.url' => 'https://notes.example.test']);
        Http::fake(['https://notes.example.test/definitions/download' => Http::response($markdown)]);

        return $this->get('/profundarium/definitions')->assertOk()->getContent();
    }
}
