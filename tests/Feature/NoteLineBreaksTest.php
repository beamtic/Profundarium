<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NoteLineBreaksTest extends TestCase
{
    public function test_source_line_breaks_remain_visible_in_notes(): void
    {
        config(['services.hedgedoc.url' => 'https://notes.example.test']);
        Http::fake(['https://notes.example.test/line-breaks/download' => Http::response(<<<'MD'
> • First degree.
>   (Thesis on: first subject)
> • Second degree.
>   (Thesis on: second subject)

First line
Second line

Explicit break  
Next line

- First item
- Second item

```text
First code line
Second code line
```
MD)]);

        $response = $this->get('/profundarium/line-breaks');
        $response->assertOk();
        $document = \Dom\HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR, 'UTF-8');
        $quote = $document->getElementsByTagName('blockquote')->item(0);
        $this->assertCount(3, $quote->getElementsByTagName('br'));
        $response->assertSee("First line<br>\nSecond line", false);
        $response->assertSee("Explicit break<br>\nNext line", false);
        $this->assertCount(2, $document->getElementsByTagName('li'));
        $code = $document->getElementsByTagName('code')->item(0);
        $this->assertSame("First code line\nSecond code line\n", $code->textContent);
        $this->assertCount(0, $code->getElementsByTagName('br'));
    }
}
