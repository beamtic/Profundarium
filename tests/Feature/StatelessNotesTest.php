<?php

/*
 * Verifies that the bundled introduction and remote notes render without a
 * working application database and without setting session cookies. The
 * introduction must make no HedgeDoc requests; remote downloads are simulated
 * and must produce the expected page title.
 *
 * Remote HTTP responses are faked so database and session independence can be
 * checked without a running HedgeDoc server. Unavailable optional endpoints
 * are simulated deliberately to verify rendering still works. The introduction
 * is checked with HTTP requests forbidden, proving it needs no remote source.
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StatelessNotesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Simulate unavailable optional metadata and alias endpoints without making real requests.
        Http::fake([
            '*/publish' => Http::response('', 404),
            '*/info' => Http::response('', 404),
        ]);
    }

    public function test_introduction_needs_no_hedgedoc_database_or_session(): void
    {
        Http::preventStrayRequests();
        // An undefined database connection makes accidental database access fail the test.
        config(['database.default' => 'unused']);

        $response = $this->get('/profundarium');

        $response->assertOk();
        $response->assertSee('Beamtic Profundarium');
        // No response cookies means this request has not started a browser session.
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
