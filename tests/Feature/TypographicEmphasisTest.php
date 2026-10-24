<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TypographicEmphasisTest extends TestCase
{
    public function test_hedgedoc_examples_render_with_the_correct_semantic_tags(): void
    {
        $response = $this->renderNote(<<<'MD'
lu~lala~

Superscript: 19^th^

Subscript: H~2~O

++Inserted text++

==Marked text==

~~Deleted text~~
MD);
        foreach (['lu<sub>lala</sub>', '19<sup>th</sup>', 'H<sub>2</sub>O', '<ins>Inserted text</ins>', '<mark>Marked text</mark>', '<del>Deleted text</del>'] as $html) {
            $response->assertSee($html, false);
        }
    }

    public function test_nested_inline_markdown_and_escaped_spaces(): void
    {
        $response = $this->renderNote(<<<'MD'
++A **bold** [link](https://example.test) and ==highlight==++

==An *italic* H~2~O and 19^th^==

^two\ words^ and ~two\ words~

^<img>^ and ~&~
MD);
        $response->assertSee('<ins>A <strong>bold</strong> <a href="https://example.test">link</a> and <mark>highlight</mark></ins>', false);
        $response->assertSee('<mark>An <em>italic</em> H<sub>2</sub>O and 19<sup>th</sup></mark>', false);
        $response->assertSee('<sup>two words</sup> and <sub>two words</sub>', false);
        $response->assertSee('<sup>&lt;img&gt;</sup> and <sub>&amp;</sub>', false);
    }

    public function test_code_escaped_unmatched_and_whitespace_markers_stay_literal(): void
    {
        $response = $this->renderNote(<<<'MD'
`19^th^ H~2~O ++insert++ ==mark==`

```markdown
19^th^ H~2~O ++insert++ ==mark==
```

    H~2~O

\^sup\^ \~sub\~ \+\+insert\+\+ \=\=mark\=\=

^two words^ ~two words~ ++ inserted ++ == marked ==

Unclosed ^sup and ~sub and ++insert and ==mark
MD);
        foreach (['<sup>', '<sub>', '<ins>', '<mark>', '<del>'] as $tag) {
            $response->assertDontSee($tag, false);
        }
        $response->assertSee('<code>19^th^ H~2~O ++insert++ ==mark==</code>', false);
        $response->assertSee('^sup^ ~sub~ ++insert++ ==mark==', false);
    }

    public function test_math_and_alerts_keep_their_existing_behavior(): void
    {
        $response = $this->renderNote(<<<'MD'
:::info
==Notice==: H~2~O and 19^th^.
:::

$x^2 + a~b + c++d == e$
MD);
        $response->assertSee('class="alert alert-info"', false);
        $response->assertSee('<mark>Notice</mark>: H<sub>2</sub>O and 19<sup>th</sup>.', false);
        $response->assertSee('\(x^2 + a~b + c++d == e\)', false);
    }

    private function renderNote(string $markdown): \Illuminate\Testing\TestResponse
    {
        config(['services.hedgedoc.url' => 'https://notes.example.test']);
        Http::fake(['https://notes.example.test/emphasis/download' => Http::response($markdown)]);

        return $this->get('/profundarium/emphasis')->assertOk();
    }
}
