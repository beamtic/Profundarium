<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NoteAliasTest extends TestCase
{
    public function test_alias_redirects_and_non_alias_urls(): void
    {
        config(['services.hedgedoc.url' => 'http://hedgedoc:3000']);
        Http::preventStrayRequests();
        Http::fake([
            'http://hedgedoc:3000/*/download' => Http::response('# Note'),
            'http://hedgedoc:3000/my-note/publish' => Http::response('', 302, ['Location' => 'https://notes.test/s/my-note']),
            'http://hedgedoc:3000/no-alias/publish' => Http::response('', 302, ['Location' => 'https://notes.test/s/short']),
            'http://hedgedoc:3000/s/short/edit' => Http::response('', 302, ['Location' => 'https://notes.test/no-alias?both']),
            'http://hedgedoc:3000/*/publish' => Http::response('', 302, ['Location' => 'https://notes.test/s/my-note']),
            'http://hedgedoc:3000/s/my-note/edit' => Http::response('', 302, ['Location' => 'https://notes.test/my-note?both']),
            '*' => Http::response('', 404),
        ]);

        foreach (['encoded-id', 'shortId'] as $id) {
            foreach (['GET', 'HEAD'] as $method) {
                $this->call($method, '/profundarium/' . $id . '?print=1')
                    ->assertStatus(302)->assertRedirect('/profundarium/my-note?print=1');
            }
        }
        $this->get('/profundarium/my-note')->assertOk();
        $this->get('/profundarium/no-alias')->assertOk();
    }
}
