<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AlertAreasTest extends TestCase
{
    public function test_alert_types_render_markdown_and_close_before_following_content(): void
    {
        $markdown = '';
        foreach (['success', 'info', 'warning', 'danger'] as $type) {
            $markdown .= ":::{$type}\n**Message**\n\n- First\n- Second\n:::\n\n";
        }
        $markdown .= 'Outside';
        $html = $this->renderNote($markdown);
        foreach (['success', 'info', 'warning', 'danger'] as $type) {
            $this->assertStringContainsString('<div class="alert alert-' . $type . '">', $html);
        }
        $this->assertSame(4, substr_count($html, '<strong>Message</strong>'));
        $this->assertSame(4, substr_count($html, '<li>First</li>'));
        $this->assertStringContainsString("</div>\n<p>Outside</p>", $html);
        $this->assertStringNotContainsString(':::', $html);
    }

    public function test_code_and_unknown_alert_types_stay_literal(): void
    {
        $html = $this->renderNote(<<<'MD'
`:::info`

```markdown
:::danger
Example
:::
```

    :::warning
    Indented code
    :::

:::unknown
Plain text
:::
MD);
        $this->assertStringNotContainsString('class="alert ', $html);
        $this->assertStringContainsString('<code>:::info</code>', $html);
        $this->assertStringContainsString(":::danger\nExample\n:::", $html);
        $this->assertStringContainsString(':::unknown', $html);
    }

    public function test_empty_unclosed_and_longer_fences(): void
    {
        $html = $this->renderNote(":::success\n:::\n\n:::: warning\nText\n::::\n\n:::info\nAt end");
        $this->assertSame(3, substr_count($html, 'class="alert alert-'));
        $this->assertStringContainsString('<p>At end</p>', $html);
        $this->assertStringNotContainsString(':::', $html);
    }

    private function renderNote(string $markdown): string
    {
        config(['services.hedgedoc.url' => 'https://notes.example.test']);
        Http::fake(['https://notes.example.test/alerts/download' => Http::response($markdown)]);

        return $this->get('/profundarium/alerts')->assertOk()->getContent();
    }
}
