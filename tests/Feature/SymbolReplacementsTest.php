<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SymbolReplacementsTest extends TestCase
{
    public function test_all_requested_symbols_are_replaced_case_insensitively(): void
    {
        $response = $this->renderNote('(c) (C) (r) (R) (tm) (TM) (p) (P) +-');
        $response->assertSee('<p>© © ® ® ™ ™ § § ±</p>', false);
    }

    public function test_replacements_work_in_emphasis_links_and_alerts(): void
    {
        $response = $this->renderNote(<<<'MD'
:::info
**(c)** [Company (TM)](https://example.test/path(c)?value=+-) ==+-==
:::
MD);
        $response->assertSee('<strong>©</strong>', false);
        $response->assertSee('href="https://example.test/path(c)?value=+-">Company ™</a>', false);
        $response->assertSee('<mark>±</mark>', false);
    }

    public function test_code_tex_escapes_and_html_attributes_stay_literal(): void
    {
        $response = $this->renderNote(<<<'MD'
`(c) (C) (r) (R) (tm) (TM) (p) (P) +-`

```text
(c) (r) (tm) (p) +-
```

    (c) +-

\(c) \(R) \(TM) \(p) \+-

$(c) + (r) + (tm) + (p) +-$

<a href="https://example.test/(c)" title="(tm) +-">Raw HTML</a>
MD);
        $response->assertSee('<code>(c) (C) (r) (R) (tm) (TM) (p) (P) +-</code>', false);
        $response->assertSee('(c) (R) (TM) (p) +-', false);
        $response->assertSee('\((c) + (r) + (tm) + (p) +-\)', false);
        $response->assertSee('title="(tm) +-"', false);
        foreach (['©', '®', '™', '§', '±'] as $symbol) {
            $response->assertDontSee($symbol, false);
        }
    }

    private function renderNote(string $markdown): \Illuminate\Testing\TestResponse
    {
        config(['services.hedgedoc.url' => 'https://notes.example.test']);
        Http::fake(['https://notes.example.test/symbols/download' => Http::response($markdown)]);

        return $this->get('/profundarium/symbols')->assertOk();
    }
}
