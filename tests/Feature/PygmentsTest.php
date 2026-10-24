<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PygmentsTest extends TestCase
{
    public function test_real_pygments_colors_code_and_escapes_html(): void
    {
        $response = $this->renderNote("```python\nprint(\"<script>alert(1)</script> Æ\")\n```", true);
        $response->assertSee('class="pygments"', false);
        $response->assertSee('<span class=', false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; Æ', false);
        $response->assertDontSee('<script>alert', false);
        $response->assertSee('/profundarium/assets/css/pygments.css', false);
        $document = \Dom\HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR, 'UTF-8');
        $this->assertSame("print(\"<script>alert(1)</script> Æ\")\n", $document->getElementsByTagName('code')[0]->textContent);
    }

    public function test_untrusted_language_and_source_cannot_execute_shell_commands(): void
    {
        $marker = sys_get_temp_dir() . '/profundarium-pygments-' . bin2hex(random_bytes(8));
        try {
            $response = $this->renderNote("```python;touch\${IFS}{$marker}\n<script>bad</script>\n```\n\n```python\n\$(touch {$marker})\n```", true);
            $response->assertSee('&lt;script&gt;bad&lt;/script&gt;', false);
            $this->assertFileDoesNotExist($marker);
        } finally {
            if (is_file($marker)) {
                unlink($marker);
            }
        }
    }

    public function test_unknown_missing_disabled_and_unlabelled_code_fall_back(): void
    {
        $response = $this->renderNote("```not-a-real-lexer\n<x>\n```\n\n```\n<y>\n```", true);
        $response->assertDontSee('class="pygments"', false);
        $response->assertSee('&lt;x&gt;', false);
        config(['highlighting.binary' => '/nonexistent/pygmentize']);
        $response = $this->renderNote("```python\n<x>\n```", true);
        $response->assertDontSee('class="pygments"', false);
        $response->assertSee('&lt;x&gt;', false);
        $response = $this->renderNote("```python\n<x>\n```", false);
        $response->assertDontSee('class="pygments"', false);
    }

    public function test_timeout_falls_back_to_escaped_code(): void
    {
        $binary = tempnam(sys_get_temp_dir(), 'profundarium-slow-pygments-');
        file_put_contents($binary, "#!/bin/sh\nsleep 5\n");
        chmod($binary, 0700);
        config(['highlighting.binary' => $binary]);
        try {
            $response = $this->renderNote("```python\n<x>\n```", true);
            $response->assertDontSee('class="pygments"', false);
            $response->assertSee('&lt;x&gt;', false);
        } finally {
            unlink($binary);
        }
    }

    private int $noteNumber = 0;

    private function renderNote(string $markdown, bool $enabled): \Illuminate\Testing\TestResponse
    {
        config(['services.hedgedoc.url' => 'https://notes.example.test', 'highlighting.enabled' => $enabled]);
        $note = 'highlight-' . ++$this->noteNumber;
        Http::fake(['https://notes.example.test/' . $note . '/download' => Http::response($markdown)]);
        return $this->get('/profundarium/' . $note)->assertOk();
    }
}
