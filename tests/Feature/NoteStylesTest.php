<?php

/*
 * Verifies that raw style elements before and after Markdown content survive
 * HTML parsing and note-link rewriting. Also checks that rewritten links keep
 * query strings and fragments and that headings receive their expected IDs.
 *
 * The download response is faked to supply exact style and link markup without
 * creating or editing a real HedgeDoc note. This isolates HTML preservation
 * from network failures and changes to server data; it does not test live
 * HedgeDoc downloads.
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NoteStylesTest extends TestCase
{
    public function test_note_styles_survive_link_rewriting(): void
    {
        config(['services.hedgedoc.url' => 'http://hedgedoc:3000']);
        Http::fake([
            'http://hedgedoc:3000/style-check/download' => Http::response(<<<'MD'
<style>.note h1 { color: rgb(123, 45, 67); }</style>

# Styled note

[Another note](/s/AnotherNote?view=1#section)

<style media="screen">.note p { font-weight: 500; }</style>
MD),
        ]);

        $response = $this->get('/profundarium/style-check');

        $response->assertOk();
        // false checks literal HTML rather than an escaped representation of the style tag.
        $response->assertSee('<style>.note h1 { color: rgb(123, 45, 67); }</style>', false);
        $response->assertSee('<style media="screen">.note p { font-weight: 500; }</style>', false);
        $response->assertSee('href="/profundarium/AnotherNote?view=1#section"', false);
        $response->assertSee('id="styled-note"', false);
    }
}
