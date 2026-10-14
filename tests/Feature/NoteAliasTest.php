<?php

/*
 * Verifies that note ID and short-ID requests receive HTTP 302 redirects to
 * the note alias, for both GET and HEAD, with query parameters preserved.
 * Also checks that alias URLs and notes without aliases render successfully.
 * HedgeDoc downloads and redirects are simulated; no real notes are changed.
 *
 * Fake responses reproduce alias and non-alias redirect chains reliably
 * without requiring a live HedgeDoc server or notes with specific IDs and
 * aliases. They test Profundarium's redirect handling; an integration check
 * against real HedgeDoc is needed to verify the assumed redirect behavior.
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NoteAliasTest extends TestCase
{
    public function test_alias_redirects_and_non_alias_urls(): void
    {
        config(['services.hedgedoc.url' => 'http://hedgedoc:3000']);
        Http::preventStrayRequests();
        // Specific no-alias redirects precede the wildcard alias response.
        // An alias is identical in published and editor URLs; generated IDs differ.
        Http::fake([
            'http://hedgedoc:3000/*/download' => Http::response('# Note'),
            'http://hedgedoc:3000/my-note/publish' => Http::response('', 302, ['Location' => 'https://notes.test/s/my-note']),
            'http://hedgedoc:3000/no-alias/publish' => Http::response('', 302, ['Location' => 'https://notes.test/s/short']),
            'http://hedgedoc:3000/s/short/edit' => Http::response('', 302, ['Location' => 'https://notes.test/no-alias?both']),
            'http://hedgedoc:3000/*/publish' => Http::response('', 302, ['Location' => 'https://notes.test/s/my-note']),
            'http://hedgedoc:3000/s/my-note/edit' => Http::response('', 302, ['Location' => 'https://notes.test/my-note?both']),
            '*' => Http::response('', 404),
        ]);

        // These are stand-in identifiers: HTTP fakes resolve them without UUID parsing.
        foreach (['encoded-id', 'shortId'] as $id) {
            foreach (['GET', 'HEAD'] as $method) {
                $this->call($method, '/profundarium/' . $id . '?print=1')
                    ->assertStatus(302)->assertRedirect('/profundarium/my-note?print=1');
            }
        }
        // A successful response here rules out redirect loops on the alias itself.
        $this->get('/profundarium/my-note')->assertOk();
        $this->get('/profundarium/no-alias')->assertOk();
    }
}
