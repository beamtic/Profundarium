<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VideoEmbedsTest extends TestCase
{
    public function test_valid_embeds_have_fixed_player_origins_and_direct_links(): void
    {
        $html = $this->renderNote("{%youtube aqz-KE-bpKQ %}\n\n{%vimeo 124148255 %}");
        $document = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
        $players = $document->getElementsByTagName('iframe');
        $this->assertCount(2, $players);
        $this->assertSame('https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ', $players[0]->getAttribute('src'));
        $this->assertSame('https://player.vimeo.com/video/124148255', $players[1]->getAttribute('src'));
        foreach ($players as $player) {
            $this->assertSame('lazy', $player->getAttribute('loading'));
            $this->assertNotSame('', $player->getAttribute('title'));
            $this->assertTrue($player->hasAttribute('allowfullscreen'));
        }
        $this->assertStringContainsString('href="https://www.youtube.com/watch?v=aqz-KE-bpKQ"', $html);
        $this->assertStringContainsString('href="https://vimeo.com/124148255"', $html);
        // Players load in the browser, without PHP requests to either provider.
        Http::assertNotSent(fn ($request) => ! str_starts_with($request->url(), 'https://notes.example.test/'));
    }

    public function test_invalid_ids_code_and_tex_cannot_create_players(): void
    {
        $html = $this->renderNote(<<<'MD'
{%youtube short %}
{%youtube aqz-KE-bpKQ" onload="alert(1) %}
{%youtube https://example.test %}
{%vimeo abc %}
{%vimeo 0 %}
{%vimeo 124148255?autoplay=1 %}
{%unknown 124148255 %}

`{%youtube aqz-KE-bpKQ %}`

```text
{%vimeo 124148255 %}
```

    {%youtube aqz-KE-bpKQ %}

${%vimeo 124148255 %}$
MD);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('<code>{%youtube aqz-KE-bpKQ %}</code>', $html);
    }

    public function test_players_work_in_alerts_and_inline_contexts(): void
    {
        $html = $this->renderNote(":::info\n{%youtube aqz-KE-bpKQ %}\n:::\n\nVideo: {% vimeo 124148255%} after.");
        $this->assertSame(2, substr_count($html, '<iframe'));
        $this->assertStringContainsString('class="alert alert-info"', $html);
        $this->assertStringContainsString('Video: <span class="video-embed">', $html);
    }

    private function renderNote(string $markdown): string
    {
        config(['services.hedgedoc.url' => 'https://notes.example.test']);
        Http::fake(['https://notes.example.test/videos/download' => Http::response($markdown)]);

        return $this->get('/profundarium/videos')->assertOk()->getContent();
    }
}
