<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FootnotesAndAbbreviationsTest extends TestCase
{
    private int $noteNumber = 0;

    public function test_named_inline_and_repeated_footnotes_have_working_links(): void
    {
        $html = $this->renderNote(<<<'MD'
Footnote 1 link[^first].
Footnote 2 link[^second].
Inline footnote^[Text of inline footnote] definition.
Duplicated footnote reference[^second].

[^first]: Footnote **can have markup**
    and continuation.

    Another paragraph.
[^second]: Footnote text.
MD);
        $this->assertStringContainsString('<strong>can have markup</strong>', $html);
        $this->assertStringContainsString('<p>Another paragraph.', $html);
        $this->assertStringContainsString('Text of inline footnote', $html);
        $document = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
        $ids = [];
        $references = 0;
        $backlinks = 0;
        foreach ($document->querySelectorAll('[id]') as $element) {
            $id = $element->getAttribute('id');
            $this->assertArrayNotHasKey($id, $ids, 'Duplicate HTML ID');
            $ids[$id] = true;
        }
        foreach ($document->querySelectorAll('a.footnote-ref, a.footnote-backref') as $link) {
            $this->assertArrayHasKey(substr($link->getAttribute('href'), 1), $ids);
            if ($link->classList->contains('footnote-ref')) {
                $references++;
            } else {
                $backlinks++;
            }
        }
        $this->assertSame(4, $references);
        $this->assertSame(4, $backlinks);
        $this->assertCount(3, $document->querySelectorAll('li.footnote'));
    }

    public function test_abbreviations_match_whole_case_sensitive_terms_across_the_document(): void
    {
        $html = $this->renderNote(<<<'MD'
This is an HTML abbreviation example. xxxHTMLyyy html HTML5 \_HTML HTML\_ remain intact.
**HTML**, (HTML), and [HTML](https://example.test/HTML).

*[HTML]: Hyper Text Markup Language
*[CSS]: Cascading Style Sheets

CSS and HTML.
MD);
        $this->assertSame(5, substr_count($html, '<abbr title="Hyper Text Markup Language">HTML</abbr>'));
        $this->assertStringContainsString('<abbr title="Cascading Style Sheets">CSS</abbr>', $html);
        $this->assertStringContainsString('xxxHTMLyyy html HTML5 _HTML HTML_', $html);
        $this->assertStringContainsString('href="https://example.test/HTML"><abbr', $html);
        $this->assertStringNotContainsString('*[HTML]:', $html);
    }

    public function test_code_tex_and_attributes_are_not_expanded(): void
    {
        $html = $this->renderNote(<<<'MD'
`HTML` and `$HTML$`.

```markdown
HTML
*[FAKE]: Not a definition
[^fake]: Not a footnote
^[Not an inline footnote]
```

    HTML

$HTML$ and $x^2$.

<span title="HTML">example</span>
![HTML](https://example.test/HTML.png)

*[HTML]: Hyper Text Markup Language
MD);
        $this->assertStringNotContainsString('<abbr', $html);
        $this->assertStringNotContainsString('class="footnotes"', $html);
        $this->assertStringContainsString('<code>HTML</code>', $html);
        $this->assertStringContainsString('\\(HTML\\)', $html);
        $this->assertStringContainsString('title="HTML"', $html);
        $this->assertStringContainsString('alt="HTML"', $html);
    }

    public function test_abbreviations_are_escaped_and_work_in_alerts_and_footnotes(): void
    {
        $html = $this->renderNote(<<<'MD'
:::info
HTML and 19^th^. See[^note].
:::

[^note]: HTML in a footnote.

*[HTML]: A "quote" & <script>alert(1)</script>
MD);
        $document = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
        $abbreviations = $document->getElementsByTagName('abbr');
        $this->assertCount(2, $abbreviations);
        $this->assertSame('A "quote" & <script>alert(1)</script>', $abbreviations[0]->getAttribute('title'));
        $this->assertCount(0, $document->getElementsByTagName('script'));
        $this->assertStringContainsString('19<sup>th</sup>', $html);
    }

    public function test_definitions_do_not_leak_between_notes(): void
    {
        $this->renderNote("HTML\n\n*[HTML]: First definition\n*[HTML]: Second definition");
        $html = $this->renderNote('HTML and undefined[^missing].');
        $this->assertStringNotContainsString('<abbr', $html);
        $this->assertStringContainsString('undefined[^missing]', $html);
    }

    private function renderNote(string $markdown): string
    {
        config(['services.hedgedoc.url' => 'https://notes.example.test']);
        $note = 'references-' . ++$this->noteNumber;
        Http::fake(['https://notes.example.test/' . $note . '/download' => Http::response($markdown)]);

        return $this->get('/profundarium/' . $note)->assertOk()->getContent();
    }
}
