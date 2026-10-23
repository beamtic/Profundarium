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
