<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DocumentEmbedsTest extends TestCase
{
    public function test_gist_and_pdf_examples_have_working_targets_and_isolated_gist_scripts(): void
    {
        $response = $this->renderNote(<<<'MD'
{%gist schacon/4277%}

{%pdf https://pdfobject.com/pdf/sample-3pp.pdf %}

{%pdf /uploads/local.pdf?download=0&view=1 %}
MD);
        $document = \Dom\HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR, 'UTF-8');
        $frames = $document->getElementsByTagName('iframe');
        $this->assertCount(3, $frames);
        $this->assertSame('allow-scripts', $frames[0]->getAttribute('sandbox'));
        $this->assertStringContainsString('https://gist.github.com/schacon/4277.js', $frames[0]->getAttribute('srcdoc'));
        $this->assertStringContainsString('Content-Security-Policy', $frames[0]->getAttribute('srcdoc'));
        $this->assertSame('https://pdfobject.com/pdf/sample-3pp.pdf', $frames[1]->getAttribute('src'));
        $this->assertSame('/uploads/local.pdf?download=0&view=1', $frames[2]->getAttribute('src'));
        $this->assertFalse($frames[1]->hasAttribute('sandbox'));
        foreach ($frames as $frame) {
            $this->assertSame('no-referrer', $frame->getAttribute('referrerpolicy'));
            $this->assertSame('lazy', $frame->getAttribute('loading'));
        }
        $this->assertCount(0, $document->getElementsByTagName('script'));
        $response->assertSee('href="https://gist.github.com/schacon/4277"', false);
        $response->assertSee('class="document-link"', false);
        Http::assertNotSent(fn ($request) => ! str_starts_with($request->url(), 'https://notes.example.test/'));
    }

    public function test_bad_targets_remain_text(): void
    {
        $response = $this->renderNote(<<<'MD'
{%gist https://example.test/payload %}
{%gist user/../../evil %}
{%gist user/123?x=1 %}
{%gist user/123" onload="alert(1) %}
{%pdf javascript:alert(1) %}
{%pdf data:text/html,evil %}
{%pdf //example.test/test.pdf %}
{%pdf https://user:pass@example.test/test.pdf %}
{%pdf /\example.test/test.pdf %}
{%pdf https://example.test/test.pdf" onload="alert(1) %}
MD);
        $response->assertDontSee('<iframe', false);
        $response->assertDontSee('srcdoc=', false);
    }

    public function test_tags_in_code_tex_and_escaped_tags_remain_literal(): void
    {
        $response = $this->renderNote(<<<'MD'
`{%gist schacon/4277%}`

```markdown
{%pdf https://example.test/sample.pdf %}
```

    {%gist schacon/4277%}

${%gist schacon/4277%}$

\{%pdf https://example.test/sample.pdf %}
MD);
        $response->assertDontSee('<iframe', false);
        $response->assertSee('<code>{%gist schacon/4277%}</code>', false);
    }

    private function renderNote(string $markdown): \Illuminate\Testing\TestResponse
    {
        config(['services.hedgedoc.url' => 'https://notes.example.test']);
        Http::fake(['https://notes.example.test/documents/download' => Http::response($markdown)]);
        return $this->get('/profundarium/documents')->assertOk();
    }
}
