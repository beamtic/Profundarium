<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MathJaxTest extends TestCase
{
    public function test_tex_survives_markdown_and_loads_mathjax(): void
    {
        $response = $this->renderNote(<<<'MD'
Euler: $e^{i\pi} + 1 = 0$ and $a_b_c + d*e*f$.

$$x < y & y > z$$

$$
\begin{aligned}
x &= \frac{a_b}{c} \\
y &= 2
\end{aligned}
$$

:::info
Inside an alert: $\sqrt{x}$
:::
MD);
        $response->assertSee('src="/profundarium/assets/js/mathjax-config.js"', false);
        $response->assertSee('/profundarium/assets/vendor/mathjax/tex-svg.js', false);
        $response->assertDontSee('cdn.jsdelivr.net', false);
        $response->assertSee('\(e^{i\pi} + 1 = 0\)', false);
        $response->assertSee('\(a_b_c + d*e*f\)', false);
        $response->assertSee('\[x &lt; y &amp; y &gt; z\]', false);
        $response->assertSee('x &amp;= \frac{a_b}{c} \\', false);
        $response->assertSee('\(\sqrt{x}\)', false);
        $response->assertDontSee('<em>', false);
    }

    public function test_code_escaped_dollars_and_currency_do_not_load_mathjax(): void
    {
        $response = $this->renderNote(<<<'MD'
`$x$` and `$$y$$`

```tex
$$
\frac{a}{b}
$$
```

    $indented$

Escaped: \$x\$. Prices: $5 and $10. Unclosed: $x.

Whitespace: $ x $.
MD);
        $response->assertDontSee('/vendor/mathjax/', false);
        $response->assertDontSee('class="mathjax-process', false);
        $response->assertSee('<code>$x$</code>', false);
        $response->assertSee('$5 and $10', false);
        $response->assertSee('$x$', false);
    }

    public function test_math_is_escaped_and_supports_list_and_multiline_contexts(): void
    {
        $response = $this->renderNote(<<<'MD'
- Equation:
  $$
  a_b + c
  $$
- Multiline inline: $\begin{cases}
  a & b \\
  c & d
  \end{cases}$

$<img src=x onerror=alert(1)>$
MD);
        $response->assertSee('class="mathjax-process math-display"', false);
        $response->assertSee('\begin{cases}', false);
        $response->assertSee('&lt;img src=x onerror=alert(1)&gt;', false);
        $response->assertDontSee('<img', false);
    }

    private function renderNote(string $markdown): \Illuminate\Testing\TestResponse
    {
        config(['services.hedgedoc.url' => 'https://notes.example.test']);
        Http::fake(['https://notes.example.test/math/download' => Http::response($markdown)]);

        return $this->get('/profundarium/math')->assertOk();
    }
}
