<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BlockquoteTagsTest extends TestCase
{
    public function test_nested_quotes_have_independent_attribution_and_colors(): void
    {
        $html = $this->renderNote(<<<'MD'
> Outer comment.
> [name=Alice] [time=2026-10-24] [color=#907bf7]
> > Inner comment.
> > [name=Bob] [time=Yesterday] [color=red]
MD);
        $document = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
        $quotes = $document->getElementsByTagName('blockquote');
        $this->assertCount(2, $quotes);
        $this->assertStringContainsString('border-left-color:#907bf7', $quotes[0]->getAttribute('style'));
        $this->assertStringContainsString('border-left-color:red', $quotes[1]->getAttribute('style'));
        $this->assertSame('Bob', $quotes[1]->querySelector('.blockquote-name')->textContent);
        $this->assertSame('Alice', $quotes[0]->lastElementChild->querySelector('.blockquote-name')->textContent);
        $this->assertStringContainsString('2026-10-24', $html);
        $this->assertStringNotContainsString('[name=', $html);
    }

    public function test_untrusted_values_are_escaped_and_bad_colors_remain_text(): void
    {
        $html = $this->renderNote(<<<'MD'
> [name=<script>alert(1)</script>] [time="quoted" & text]
> [color=red; background:url(https://example.test)] [unknown=value]
MD);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('style=', $html);
        $this->assertStringContainsString('[color=red; background:url(https://example.test)', strip_tags(html_entity_decode($html)));
        $this->assertStringContainsString('[unknown=value]', $html);
    }

    public function test_code_tex_and_tags_outside_quotes_remain_literal(): void
    {
        $html = $this->renderNote(<<<'MD'
[name=Outside]

> `[name=Code]`
>
> ```text
> [color=red]
> ```
>
> $\text{[name=Math]}$
MD);
        $this->assertStringNotContainsString('blockquote-attribution', $html);
        $this->assertStringNotContainsString('border-left-color', $html);
        $this->assertStringContainsString('[name=Outside]', $html);
        $this->assertStringContainsString('<code>[name=Code]</code>', $html);
    }

    private function renderNote(string $markdown): string
    {
        config(['services.hedgedoc.url' => 'https://notes.example.test']);
        Http::fake(['https://notes.example.test/quotes/download' => Http::response($markdown)]);
        return $this->get('/profundarium/quotes')->assertOk()->getContent();
    }
}
