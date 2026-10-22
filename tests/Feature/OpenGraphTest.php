<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// Fake downloads exercise rendered page metadata without depending on live notes.
class OpenGraphTest extends TestCase
{
    private function fakeNote(string $markdown): void
    {
        config(['services.hedgedoc.url' => 'https://notes.example.test']);
        Http::preventStrayRequests();
        Http::fake([
            '*/download' => Http::response($markdown),
            '*/publish' => Http::response('', 404),
            '*/info' => Http::response('', 404),
        ]);
    }

    public function test_hedgedoc_open_graph_is_rendered_in_the_head(): void
    {
        $this->fakeNote(<<<'MD'
---
tags: index
robots: noindex, nofollow
opengraph:
  title: The Collaboration
  image: https://hedgedoc.beamtic.net/uploads/f60abde7-d8a9-4a61-8bbd-2d74c691a856.png
  type: article
---
# The Collaboration

The Voyage
MD);
        $response = $this->get('/profundarium/example');
        $response->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $document = \Dom\HTMLDocument::createFromString($response->getContent());
        $tags = [];
        foreach ($document->head->getElementsByTagName('meta') as $tag) {
            if ($tag->hasAttribute('property')) {
                $tags[$tag->getAttribute('property')] = $tag->getAttribute('content');
            }
        }
        $this->assertSame([
            'og:title' => 'The Collaboration',
            'og:image' => 'https://hedgedoc.beamtic.net/uploads/f60abde7-d8a9-4a61-8bbd-2d74c691a856.png',
            'og:type' => 'article',
        ], $tags);
        $this->assertStringNotContainsString('opengraph:', $document->body->textContent);
    }

    public function test_yaml_values_are_escaped_and_lists_produce_repeated_tags(): void
    {
        $this->fakeNote(<<<'MD'
---
opengraph:
  title: 'A "quote" & <script>alert(1)</script>'
  description: >-
    First line:
    second line
  image:
    - https://example.test/one.png
    - https://example.test/two.png
  image:width: 1200
  invalid: {nested: value}
  'bad"property': unsafe
---
# Heading
MD);
        $response = $this->get('/profundarium/example');
        $response->assertOk();
        $response->assertSee('content="A &quot;quote&quot; &amp; &lt;script&gt;alert(1)&lt;/script&gt;"', false);
        $response->assertSee('property="og:description" content="First line: second line"', false);
        $response->assertSee('property="og:image" content="https://example.test/one.png"', false);
        $response->assertSee('property="og:image" content="https://example.test/two.png"', false);
        $response->assertSee('property="og:image:width" content="1200"', false);
        $response->assertDontSee('og:invalid', false);
        $response->assertDontSee('bad&quot;property', false);
    }

    public function test_missing_or_invalid_metadata_does_not_break_rendering(): void
    {
        foreach (["# Heading", "---\nopengraph: [broken\n---\n# Heading", "---\nopengraph: false\n---\n# Heading"] as $markdown) {
            $this->fakeNote($markdown);
            $this->get('/profundarium/example')->assertOk()
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
                ->assertSee('<title>Heading</title>', false)
                ->assertDontSee('property="og:', false);
        }
    }
}
