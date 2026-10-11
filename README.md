# Beamtic Profundarium

Profundarium displays Markdown notes from HedgeDoc as HTML. It makes notes easier
for search engines to index, cleaner to print, and easy to save as PDF using
the browser's print menu.

This repository contains the Laravel application only.
[Sandboxer](https://github.com/Turbulentarius/sandboxer) provides the Docker
Compose environment used to develop it, including PHP, Apache, and HedgeDoc.
Sandboxer's containers and configuration live in that separate repository.

## Requirements

- PHP 8.4.1 or newer, with DOM and the extensions required by Composer.
- Composer 2.
- A reachable HedgeDoc instance with notes that can be downloaded without signing in.

Rendering pages does not need a database, migrations, a queue worker, Node.js,
or an asset build. The optional media commands require PostgreSQL access and
PHP's `pdo_pgsql` extension. CSS and fonts are served directly from `public/`.

## Develop with Sandboxer

For a fresh checkout, clone Profundarium into Sandboxer's `www/laravel` directory
**before** starting Sandboxer. Its setup service leaves existing applications alone.
If that directory already contains the generated example or your own work, move
it aside first; do not overwrite it.

```sh
git clone https://github.com/Turbulentarius/sandboxer.git
cd sandboxer
git clone https://github.com/Turbulentarius/profundarium.git www/laravel
docker compose up -d --build
docker compose exec -w /srv/sandboxer/laravel php composer setup
```

Run `composer setup` once for a new checkout: it installs dependencies, copies
`.env.example` if `.env` is missing, and generates the application key.
For subsequent dependency installs, use `composer install` instead.

Open **http://laravel.localhost/profundarium** for the introduction. Create a note in
**http://hedgedoc.localhost**, make it publicly readable, then open
`http://laravel.localhost/profundarium/<note-id>` using its HedgeDoc ID or alias.
Sandboxer's other example applications and database services are independent of
Profundarium; the optional media commands connect directly to a separately
configured HedgeDoc PostgreSQL database.

## Configure the note source

Edit the application's `.env` file (not Sandboxer's root `.env`):

```dotenv
APP_NAME="Beamtic Profundarium"
APP_URL=http://laravel.localhost
HEDGEDOC_URL=http://hedgedoc:3000
```

`HEDGEDOC_URL` is the HedgeDoc base URL reachable from the PHP process. The
`hedgedoc:3000` hostname works on Sandboxer's internal Docker network. When
running outside that network, set a reachable URL, for example
`https://notes.example.com`. Profundarium fetches `<base-url>/<note-id>/download`.

Note-link rewriting recognizes the incoming request's host automatically, as
well as `HEDGEDOC_URL`. This supports a shared public hostname for HedgeDoc and
Profundarium while fetching notes over Docker's internal network. The reverse
proxy must preserve the public Host header (or supply forwarded host information
through Laravel's configured trusted proxies). HTTP and HTTPS links are both
recognized. A different public HedgeDoc hostname cannot be inferred from the
request; unrelated hosts are left unchanged.

After changing settings, clear any cached configuration:

```sh
docker compose exec -w /srv/sandboxer/laravel php php artisan config:clear
```

Keep `.env` private. The repository includes only `.env.example`, without an
application key or personal credentials.

## Run without Sandboxer

From the application directory, with PHP and Composer installed:

```sh
composer setup
# Set HEDGEDOC_URL and APP_URL in .env for your environment.
php artisan serve
```

Visit `http://127.0.0.1:8000/profundarium`. For another web server, its document root must
be `public/`; `storage/` and `bootstrap/cache/` must be writable by PHP.
Use `APP_DEBUG=false` when making an instance publicly accessible.

## Static asset URLs

CSS and fonts live in `public/profundarium/assets/css/` and
`public/profundarium/assets/fonts/`. The stylesheet uses the root-relative URL
`/profundarium/assets/css/notes.css`, so HTTPS pages also load assets over HTTPS.
Relative font URLs resolve under the same asset prefix.

Sandboxer's Apache and `php artisan serve` serve these files directly from
`public/`, without aliases. For Nginx serving the public directory at
`/srv/profundarium/public`, use:

```nginx
location ^~ /profundarium/assets/ {
    root /srv/profundarium/public;
    try_files $uri =404;
}
```

A containerized Nginx must have the application's public directory mounted at
that path (read-only is sufficient). Replace any previous asset alias to
`/srv/profundarium/public/` with this mapping when deploying the moved files.

## Routes and notes

- `/profundarium` renders the bundled introduction in `resources/notes/default.md`.
- `/profundarium/<note-id>` renders a note from the configured HedgeDoc instance.
- `/up` is Laravel's application health endpoint; it does not check HedgeDoc.

The first H1 supplies the page title. Links in the form `/s/<note-id>` and
matching absolute HedgeDoc share links are rewritten to `/profundarium/<note-id>`.
The optional `robots` field in a note's leading front matter becomes the
`X-Robots-Tag` response header.

Profundarium forwards no login credentials to HedgeDoc. Its renderer preserves raw
HTML, including note `<style>` blocks, so configure a source whose note content
you trust; it is not a general-purpose HTML sanitizer.

Images support HedgeDoc's numeric size suffixes: `![Alt text](URL =380x)`
sets width, `=x240` sets height, and `=380x240` sets both. Alt text is preserved,
and image examples inside inline or fenced code remain literal.

To print or save a note as PDF, use your browser's print menu.

## Tests

Inside Sandboxer, run from its repository root:

```sh
docker compose exec -w /srv/sandboxer/laravel php composer test
```

With local PHP, run `composer test` from this repository. Tests fake HedgeDoc HTTP
responses; media tests use an isolated in-memory SQLite database and temporary
uploads, so they also require `pdo_sqlite`. No live HedgeDoc data is changed.

## Unused HedgeDoc images

The optional `media:unused` and `media:cleanup` commands read **all** rows of
HedgeDoc 1.x's quoted `Notes` table, including private and soft-deleted notes.
Only current stored `content` is scanned; historical revisions are not included.
The PostgreSQL connection uses a read-only, repeatable-read snapshot. The
database account must have SELECT access to every note: do not use a role with
row-level restrictions that hide private notes.

Configure these variables in Profundarium's `.env`:

```dotenv
HEDGEDOC_PUBLIC_URL=https://notes.example.com
HEDGEDOC_UPLOADS_PATH=/srv/hedgedoc/uploads
HEDGEDOC_DB_HOST=database
HEDGEDOC_DB_PORT=5432
HEDGEDOC_DB_DATABASE=hedgedoc
HEDGEDOC_DB_USERNAME=hedgedoc
HEDGEDOC_DB_PASSWORD=your-database-password
HEDGEDOC_DB_SCHEMA=public
HEDGEDOC_DB_SSLMODE=prefer
```

`HEDGEDOC_PUBLIC_URL` is the public base URL (include any deployment subpath),
independent of the internal HTTP fetch URL `HEDGEDOC_URL`. Files map to
`<public-base>/uploads/<relative-file-path>`, matching HedgeDoc 1.x's filesystem
upload backend. See [HedgeDoc configuration](https://docs.hedgedoc.org/configuration/).
`HEDGEDOC_UPLOADS_PATH` must be an absolute directory accessible to the PHP
process, with no symlinks or dot segments. Mount or share HedgeDoc's filesystem
uploads at that location yourself; this application does not manage containers
or mounts. Listing requires read/search permission throughout the tree;
deletion requires write permission on the containing directories. Use a
read-only mount when only listing. PostgreSQL must be reachable and `pdo_pgsql`
installed (on Alpine, use the matching PHP package, for example
`php85-pdo_pgsql` with PHP 8.5). Then run `php artisan config:clear`.

```sh
php artisan media:unused
php artisan media:cleanup
# Explicit unattended authorization:
php artisan media:cleanup --no-interaction --force
```

Inside Sandboxer, prefix these commands with
`docker compose exec -w /srv/sandboxer/laravel php` after configuring your local
PHP image, uploads mount, and database access. These environment changes belong
in your own local configuration.

Example listing:

```text
Scanning HedgeDoc notes...
Notes scanned: 248
Images scanned: 137
Unused images: 3
Reclaimable: 6.4 MB
https://notes.example.com/uploads/image-001.png
https://notes.example.com/uploads/image-002.jpg
https://notes.example.com/uploads/image-003.webp
```

**Cleanup permanently deletes files.** Review the URLs and back up uploads
before answering the default-no confirmation. Non-interactive cleanup refuses
deletion without `--force`. A fresh scan after confirmation skips files that
became referenced or changed. Database, parsing, and enumeration failures abort
cleanup; deletion failures return a nonzero status and identify failed URLs.
Private note contents and database exception details are never printed.
Rejected image references are logged at warning level through Laravel's configured
logging channel. With the `single` channel, inspect `storage/logs/laravel.log`.
The log includes the note ID, public note URL, and extracted image URL for debugging; keep logs private because URLs
can contain private paths or tokens.

Markdown inline, full/collapsed/shortcut reference images, HedgeDoc size
suffixes, HTML `img src`, and HTML `srcset` are parsed with the existing
CommonMark and HTML parsers. Matching handles absolute, protocol-relative,
root-relative, and base-relative upload URLs, percent encoding, queries, and
fragments. Other hosts and code examples are ignored. Matching conservatively
preserves same-host references using a different HTTP(S) scheme or port,
including URLs stored before a reverse-proxy change. Recursive enumeration
includes hidden files and recognizes these extensions (case-insensitively):
jpg, jpeg, png, gif, webp, svg, avif, bmp, ico, tif, tiff, apng. Non-image
extensions are left alone; this is extension-based classification, not MIME
validation. Symlinks anywhere in the upload tree abort scanning.

References generated by JavaScript, CSS background URLs, HTML `base` overrides,
custom Markdown plugins, external host aliases/CDNs, and images used only by
historical revisions or other applications are not detected. Unsaved edits in
HedgeDoc's memory are not visible in PostgreSQL. Save notes and pause editing,
uploads, and other filesystem writers while cleaning up: the database snapshot
cannot make filesystem changes atomic, and a narrow race remains between final
path validation and unlink. Do not run cleanup against directories writable by
untrusted concurrent processes. Review `media:unused` first if notes use any of
these unsupported reference forms.

## Repository contents

Application code, static assets, configuration templates, tests, and
`composer.lock` are versioned. Installed dependencies, local environment files,
databases, logs, caches, and Sandboxer's development environment are excluded.

See [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md) for the adapted HedgeDoc CSS,
Roboto fonts, and dependency licenses. The Laravel skeleton's generic project
license declaration has been removed; those third-party notices do not assign a
license to original Profundarium code.
