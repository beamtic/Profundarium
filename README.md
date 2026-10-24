# Beamtic Profundarium

Profundarium renders public HedgeDoc Markdown notes as HTML for reading,
printing, and saving as PDF through your browser.

## Container deployment

The [published PHP-FPM image](https://github.com/beamtic/Profundarium/pkgs/container/profundarium)
includes the application and production Composer dependencies. It requires a
separate web server.

See [DOCKER.md](DOCKER.md) for deployment instructions and
[compose.example.yml](compose.example.yml) for a working Nginx example, including
shared public files and optional uploads/static mounts.

## Local development

Requirements: PHP 8.4.27+ on the 8.4 branch, or PHP 8.5.12+ on newer PHP 8
branches, with the extensions required by Composer; Composer 2; and a reachable
HedgeDoc instance. These minimums include the
[PHP2Shell security fixes](https://www.php.net/archive/2026.php#2026-10-21-5).
Rendering notes does not require a database, Node.js, or an asset build.

```sh
composer setup
# Configure APP_URL and HEDGEDOC_URL in .env.
php artisan serve
```

`composer setup` installs dependencies, creates `.env` if missing, and generates
an application key. Use `composer install` for subsequent dependency installs.

Example application settings:

```dotenv
APP_URL=http://127.0.0.1:8000
HEDGEDOC_URL=https://notes.example.com
```

`HEDGEDOC_URL` must be reachable from PHP. Keep `.env` private and set
`APP_DEBUG=false` on publicly accessible instances. If configuration is cached,
run `php artisan config:clear` after changing settings.

### Using Sandboxer

[Sandboxer](https://github.com/Turbulentarius/sandboxer) provides the separate
Docker development environment. Clone Profundarium before starting it so its
setup service preserves this application:

```sh
git clone https://github.com/Turbulentarius/sandboxer.git
cd sandboxer
git clone https://github.com/beamtic/Profundarium.git www/laravel
docker compose up -d --build
docker compose exec -w /srv/sandboxer/laravel php composer setup
```

Use a fresh `www/laravel` directory; do not overwrite existing work. Open
`http://laravel.localhost/profundarium/`. In the application's `.env`, use
`APP_URL=http://laravel.localhost` and `HEDGEDOC_URL=http://hedgedoc:3000`.

## Usage

- `/profundarium/` displays the introduction.
- `/profundarium/<note-id>` displays a public HedgeDoc note using its ID or alias.
- `/up` checks the application health, without checking HedgeDoc connectivity.

Use your browser's print menu to print or save a note as PDF. Notes must be
readable without signing in; Profundarium does not forward login credentials.
The renderer preserves raw HTML, so use a trusted note source.

### Alert areas

HedgeDoc alert areas support `success`, `info`, `warning`, and `danger`:

```markdown
:::info
This is a message with **Markdown** support.
:::
```

Each alert has a matching background, border, and text color when reading or
printing. Alert contents can include paragraphs, lists, and other Markdown.
Markers inside code examples remain literal text.

### Mathematics

MathJax renders HedgeDoc's `$...$` inline expressions and `$$...$$` display
equations, including multiline TeX:

```markdown
Euler's identity: $e^{i\pi} + 1 = 0$.

$$
x = \frac{-b \pm \sqrt{b^2-4ac}}{2a}
$$
```

TeX is preserved without Markdown formatting. Code examples, escaped dollars
(`\$`), and ordinary dollar amounts stay literal. Inline delimiters must touch
their contents and the closing dollar must not be followed by a digit.
MathJax 4.0.0 loads from jsDelivr only on pages containing math; rendering needs
JavaScript and browser access to that CDN. Without it, the TeX remains visible.
Wait for equations to finish rendering before printing or saving as PDF.

### Extended emphasis

HedgeDoc's extended emphasis syntax is supported:

| Markdown | Result |
| --- | --- |
| `19^th^` | Superscript (`19<sup>th</sup>`) |
| `H~2~O` | Subscript (`H<sub>2</sub>O`) |
| `++Inserted text++` | Inserted text (`<ins>`) |
| `==Marked text==` | Highlighted text (`<mark>`) |
| `~~Deleted text~~` | Deleted text (`<del>`) |

Single tildes produce subscript, including `lu~lala~`. Superscript and subscript
contents cannot contain unescaped spaces; use `^two\ words^` when needed.
Inserted and marked text can contain other inline Markdown. Code examples and
escaped markers remain literal.

### Symbol replacements

In ordinary text, `(c)` becomes ©, `(r)` becomes ®, `(tm)` becomes ™,
`(p)` becomes §, and `+-` becomes ±. Letter case does not matter.
These replacements leave code, TeX, and link destinations unchanged.
Escape the opening character (for example, `\(c)` or `\+-`) for literal text.

Straight double and single quotation marks become curly quotes in ordinary
text: `"quoted"` becomes “quoted” and `'quoted'` becomes ‘quoted’. Apostrophes
in contractions become curly too (`don't` → don’t). Escape quotes with a
backslash to keep them straight. Code, TeX, link destinations, and HTML
attributes are preserved.

### Note metadata

Optional YAML front matter controls indexing and link previews:

```yaml
---
robots: noindex, nofollow
opengraph:
  title: My note
  image: https://notes.example.com/uploads/preview.png
  type: article
---
```

The default indexing policy is `noindex, nofollow`. Use absolute public URLs
for preview images.

## Media commands

Optional commands list and remove unused HedgeDoc uploads:

```sh
php artisan media:unused
php artisan media:cleanup
```

These need database access and an uploads mount. Read [MEDIA.md](MEDIA.md) for
configuration, supported references, and cleanup limitations before using them.
Cleanup permanently deletes files.

## Tests

Run `composer test`, or from Sandboxer's repository root:

```sh
docker compose exec -w /srv/sandboxer/laravel php composer test
```

Tests use fake HedgeDoc responses, temporary files, and in-memory SQLite
(requires `pdo_sqlite`). They do not modify live HedgeDoc data.

## License

Original Profundarium code is copyright (C) 2026 Jacob and licensed under
[GPL-3.0-or-later](LICENSE). Third-party assets and dependencies retain their
own licenses; see [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).

The optional media commands support HedgeDoc databases on PostgreSQL, SQLite,
and MySQL/MariaDB. Set `HEDGEDOC_DB_CONNECTION` and the corresponding database
settings; see [database selection](DOCKER.md#database-selection). The runtime
needs `pdo_pgsql`, `pdo_sqlite`, or `pdo_mysql` respectively.
