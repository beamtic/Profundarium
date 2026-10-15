<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NoteTypographyTest extends TestCase
{
    public function test_dashes_and_entities_render_in_text_and_table_of_contents(): void
    {
        config(['services.hedgedoc.url' => 'https://notes.example.test']);
        Http::fake(['https://notes.example.test/typography/download' => Http::response(<<<'MD'
[TOC]

# A &quot;title&quot; -- &amp; &#169; &lt;em&gt;

Text -- dash --- dash &quot;quoted&quot; &amp; &#xA9; &lt;script&gt;

`code -- &quot;`

```text
code -- &quot;
```

Escaped \-\- and single - and four ----.

[A link](https://example.test/a--b?q=one&amp;other=two)
MD)]);

        $response = $this->get('/profundarium/typography');
        $response->assertOk();
        $document = \Dom\HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR, 'UTF-8');
        $heading = $document->getElementsByTagName('h1')->item(0);
        $this->assertSame('A "title" — & © <em>', $heading->textContent);
        $tocLink = $document->getElementsByTagName('nav')->item(0)->getElementsByTagName('a')->item(0);
        $this->assertSame($heading->textContent, $tocLink->textContent);
        $this->assertSame('#' . $heading->getAttribute('id'), $tocLink->getAttribute('href'));
        $this->assertStringContainsString('Text — dash — dash "quoted" & © <script>', $document->body->textContent);
        foreach ($document->getElementsByTagName('code') as $code) {
            $this->assertSame('code -- &quot;', trim($code->textContent));
        }
        $this->assertStringContainsString('Escaped -- and single - and four ----.', $document->body->textContent);
        $response->assertSee('href="https://example.test/a--b?q=one&amp;other=two"', false);
        $this->assertCount(0, $document->getElementsByTagName('script'));
    }
}
