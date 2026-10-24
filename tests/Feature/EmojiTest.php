<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmojiTest extends TestCase
{
    public function test_examples_aliases_and_inline_contexts(): void
    {
        $response = $this->renderNote(<<<'MD'
:smile: :smiley: :cry: :wink: :+1: :heart:

:::info
**:tada:** [Hello :wave:](https://example.test/:smile:)
:::
MD);
        $response->assertSee('😄 😃 😢 😉 👍 ❤️', false);
        $response->assertSee('<strong>🎉</strong>', false);
        $response->assertSee('href="https://example.test/:smile:">Hello 👋</a>', false);
    }

    public function test_unknown_escaped_code_tex_and_attributes_stay_literal(): void
    {
        $response = $this->renderNote(<<<'MD'
:unknown_name: :SMILE: \:smile: :)

`:smile:`

```text
:smile:
```

    :smile:

$\text{:smile:}$

<span title=":smile:">HTML</span>
MD);
        $response->assertDontSee('😄', false);
        $response->assertSee(':unknown_name: :SMILE: :smile: :)', false);
        $response->assertSee('<code>:smile:</code>', false);
        $response->assertSee('title=":smile:"', false);
        $response->assertSee('\\(\\text{:smile:}\\)', false);
    }

    private function renderNote(string $markdown): \Illuminate\Testing\TestResponse
    {
        config(['services.hedgedoc.url' => 'https://notes.example.test']);
        Http::fake(['https://notes.example.test/emoji/download' => Http::response($markdown)]);
        return $this->get('/profundarium/emoji')->assertOk();
    }
}
