<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StatelessNotesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([
            '*/publish' => Http::response('', 404),
            '*/info' => Http::response('', 404),
        ]);
    }

    public function test_introduction_needs_no_hedgedoc_database_or_session(): void
    {
        Http::preventStrayRequests();
        config(['database.default' => 'unused']);

        $response = $this->get('/profundarium');

        $response->assertOk();
        $response->assertSee('Beamtic Profundarium');
        $this->assertSame([], $response->headers->getCookies());
        Http::assertNothingSent();
    }

    public function test_remote_notes_need_no_database_or_session(): void
    {
        config([
            'services.hedgedoc.url' => 'http://hedgedoc:3000',
            'database.default' => 'unused',
        ]);
        Http::preventStrayRequests();
        Http::fake(['http://hedgedoc:3000/example/download' => Http::response('# Example')]);

        $response = $this->get('/profundarium/example');

        $response->assertOk();
        $response->assertSee('<title>Example</title>', false);
        $this->assertSame([], $response->headers->getCookies());
    }
}
