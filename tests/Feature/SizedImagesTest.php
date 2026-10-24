<?php

/*
 * Verifies rendering of HedgeDoc image size suffixes with width, height, or
 * both dimensions, including pasted image syntax and ordinary images. Checks
 * that Unicode, quotes, and ampersands survive in alternative text and that
 * image syntax inside inline and fenced code remains text rather than images.
 *
 * The fake download contains a fixed set of image syntax edge cases so results
 * do not depend on a live HedgeDoc server or an editable note. This tests the
 * renderer against known Markdown input, not real HedgeDoc HTTP behavior.
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SizedImagesTest extends TestCase
{
    public function test_sized_images_preserve_alt_text_and_dimensions(): void
    {
        config(['services.hedgedoc.url' => 'https://notes.example.test']);
        Http::fake(['https://notes.example.test/images/download' => Http::response(<<<'MD'
![MAGA kid turning and twisting bricks for his brick sorter](https://hedgedoc.beamtic.net/uploads/e704dd05-3a4f-4c4c-9443-1b4b8aa5d7d9.png =380x)

![Tall](https://example.test/tall.png =x240)

![Both](https://example.test/both.png =380x240)

![Pasted]\([https://example.test/pasted.png](https://example.test/pasted.png) =380x)

![Ordinary alt](https://example.test/plain.png)

![Æble "quoted" & text](https://example.test/escaped.png =40x)

![Square](https://example.test/square.png =256x256)

`![Inline code](https://example.test/code.png =380x)`

```markdown
![Fenced code](https://example.test/fence.png =380x)
```
MD, 200, ['Content-Type' => 'text/markdown'])]);

        $response = $this->get('/profundarium/images');
        $response->assertOk();
        // DOM attributes let us check dimensions and decoded alt text independently of serialization.
        $document = \Dom\HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR, 'UTF-8');
        $images = $document->getElementsByTagName('img');
        // The two code examples must not add image elements to the seven real images.
        $this->assertCount(7, $images);
        $this->assertSame('MAGA kid turning and twisting bricks for his brick sorter', $images[0]->getAttribute('alt'));
        $this->assertSame('380', $images[0]->getAttribute('width'));
        $this->assertFalse($images[0]->hasAttribute('height'));
        $this->assertSame('240', $images[1]->getAttribute('height'));
        $this->assertFalse($images[1]->hasAttribute('width'));
        $this->assertSame('380', $images[2]->getAttribute('width'));
        $this->assertSame('240', $images[2]->getAttribute('height'));
        $this->assertSame('https://example.test/pasted.png', $images[3]->getAttribute('src'));
        $this->assertSame('Ordinary alt', $images[4]->getAttribute('alt'));
        $this->assertSame('Æble "quoted" & text', $images[5]->getAttribute('alt'));
        $this->assertSame('256', $images[6]->getAttribute('width'));
        $this->assertSame('256', $images[6]->getAttribute('height'));
        $response->assertSee('![Inline code](https://example.test/code.png =380x)', false);
        $this->assertStringContainsString('![Fenced code](https://example.test/fence.png =380x)', $document->getElementsByTagName('pre')[0]->textContent);
    }
}
