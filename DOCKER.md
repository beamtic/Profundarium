# Profundarium PHP-FPM image

The published image contains PHP-FPM, Profundarium, and production Composer
dependencies. Alpine's `py3-pygments` package and Python runtime provide
server-side fenced-code highlighting. They require no compiler, glibc layer,
or separate service. This increases the runtime image size compared with the
previous PHP-only image. `PYGMENTS_ENABLED=false` disables highlighting but
does not remove those installed packages. It does not contain Nginx or a process supervisor. PHP-FPM runs
in the foreground on port 9000. This is FastCGI, not HTTP; do not publish that
port to the internet. Logs go to stderr.

Build from the repository root:

```sh
docker build -t profundarium .
```

## Example with a separate Nginx container

`compose.example.yml` is a starting point for your own deployment. It uses the
official Alpine Nginx image and the example configuration in
`docker/nginx/nginx.conf`. Both services have read-only root filesystems and
writable temporary storage under `/tmp`. Adapt networking, TLS termination,
HedgeDoc connectivity, and application settings for your environment.

Generate a stable Laravel key and put it in the Compose `.env` beside the sample:

```sh
docker run --rm ghcr.io/beamtic/profundarium:0.2.0 php artisan key:generate --show
```

```dotenv
PROFUNDARIUM_APP_KEY=base64:YOUR_GENERATED_VALUE
PROFUNDARIUM_APP_URL=http://localhost:8080
HEDGEDOC_URL=http://your-reachable-hedgedoc:3000
```

Then start the sample:

```sh
docker compose -f compose.example.yml up -d
```

Open `http://localhost:8080/profundarium/`. For a local build, set
`PROFUNDARIUM_IMAGE=profundarium` in the Compose `.env`. Use a tested version or
digest in production. This example requires the PHP-FPM-only image; the older
combined HTTP image is incompatible with it until this change is published.

The `assets-init` service runs once to copy the image's public files into the dedicated
`public` volume. Nginx mounts that volume read-only at the same public-directory
path used by PHP-FPM. It needs no source checkout or shared application-code
mount. The asset service clears only that generated volume before copying;
do not use it for uploads or administrator files.

When updating, pull the new image, stop the web service, refresh the generated
public files, and recreate the application and web services:

```sh
docker compose -f compose.example.yml pull
docker compose -f compose.example.yml stop web
docker compose -f compose.example.yml run --rm assets-init
docker compose -f compose.example.yml up -d --force-recreate profundarium web
```

The backend image supplies an FPM health check. The web service checks `/up`
over HTTP, covering Nginx, FastCGI, and Laravel. The `profundarium` account defaults
to UID/GID 1000, configurable with APP_UID and APP_GID build arguments.
Temporary Laravel storage defaults to `/tmp/profundarium-storage`; no application
`.env` is baked into the image.

## Non-root operation

PHP-FPM runs as `profundarium` and Nginx runs as `nginx`. The sample services
use read-only root filesystems, drop all Linux capabilities, and enable
no-new-privileges. Temporary files are stored under `/tmp`.

Mounted uploads, static files, and Nginx configuration must be readable by their
service accounts. Any writable PHP storage mount must be writable by
`profundarium`. The entrypoint does not change host-directory ownership.

## Optional uploaded and administrator files

Uncomment the example web-service mounts as needed:

```yaml
volumes:
  - ./uploads:/hedgedoc/public/uploads:ro
  - ./static:/srv/static:ro
```

The Nginx example serves those directories at `/uploads/` and `/static/`.
They are optional, and missing files return 404. Directory listings, hidden
paths, PHP files, and symlinks are blocked. Files must be readable by Nginx's
`nginx` worker account. Only mount files intended for public access.

These mounts belong on the web service. If using the optional media commands,
also mount uploads into the PHP service and set HEDGEDOC_UPLOADS_PATH to that
container path; cleanup requires a writable upload mount. Keep database files
outside the publicly served directories.

Existing absolute image URLs are not rewritten. If these endpoints should be
served under your HedgeDoc domain, route its `/uploads/` and `/static/` requests
to the web service in your existing reverse proxy. Configure any custom redirects
in your own Nginx configuration.

## Database selection

The same image includes PostgreSQL, MySQL/MariaDB, and SQLite PDO drivers. PostgreSQL remains
the default. These settings select the existing HedgeDoc database used by the
optional media commands; ordinary note rendering uses HEDGEDOC_URL.

For PostgreSQL, add these variables to the service's `environment`:

```yaml
HEDGEDOC_DB_CONNECTION: pgsql
HEDGEDOC_DB_HOST: postgres
HEDGEDOC_DB_PORT: "5432"
HEDGEDOC_DB_DATABASE: hedgedoc
HEDGEDOC_DB_USERNAME: hedgedoc
HEDGEDOC_DB_PASSWORD: ${HEDGEDOC_DB_PASSWORD:?Set database password}
```

For MySQL or MariaDB, use `mysql` (or `mariadb`) and port 3306:

```yaml
HEDGEDOC_DB_CONNECTION: mysql
HEDGEDOC_DB_HOST: mariadb
HEDGEDOC_DB_PORT: "3306"
HEDGEDOC_DB_DATABASE: hedgedoc
HEDGEDOC_DB_USERNAME: hedgedoc
HEDGEDOC_DB_PASSWORD: ${HEDGEDOC_DB_PASSWORD:?Set database password}
```

The media scanner uses a read-only, repeatable-read transaction. The account
must be able to select all rows from HedgeDoc's `Notes` table.

For SQLite, point to the existing HedgeDoc SQLite file inside the container:

```yaml
services:
  profundarium:
    image: profundarium
    environment:
      HEDGEDOC_DB_CONNECTION: sqlite
      HEDGEDOC_DB_DATABASE: /srv/hedgedoc/database.sqlite
    volumes:
      - ./hedgedoc-data:/srv/hedgedoc:ro
```

Merge these settings with the application environment shown above. Replace the
host directory and database filename with those used by your HedgeDoc instance.
Mount the containing directory so SQLite can access any WAL and shared-memory
sidecar files. Ensure the profundarium account can read the database and required sidecars;
read-only access to a live WAL database depends on the database's journal state
and sidecar availability. Profundarium does not create or migrate this database.

## GitHub Actions

`.github/workflows/container.yml` runs application tests, builds the runtime image,
and checks the read-only FPM runtime plus the separate Nginx sample on pull
requests targeting `main` and pushes to `main`.
Only push events publish images to GitHub Container Registry. Manual workflow
runs validate the selected ref without publishing. Builds currently target the
GitHub Ubuntu runner's native Linux AMD64 architecture.

Published tags under `ghcr.io/<owner>/profundarium` are:

- `main` for the current successful main-branch build.
- `sha-<full-commit>` for every published build.
- `1.2.3` when a release tag `v1.2.3` is pushed.

The workflow uses the built-in GITHUB_TOKEN with package write permission;
no registry password needs to be stored. Repository or organization policies
must permit Actions to publish packages. Package visibility is managed in GitHub;
private packages require authentication on machines pulling the image.

Use a published image reference in Compose, preferably pinned to its digest for
production. Publishing does not deploy or restart any environment. Protect `main`
and require the workflow checks before merging if failed builds should block merges.

## Preparing a release

`VERSION` records the release being prepared. The release being prepared is `0.4.1`; references in the sample become usable after that release is published.
Both sample image references must match VERSION. CI checks this and rejects
release tags that do not match it.

For the next release, increment VERSION once and update both sample defaults.
Use patch versions for fixes and minor versions for new features or incompatible
changes while below 1.0. Do not increment for every development commit.
After the maintainer approves a tested release commit, create and push its
matching `vX.Y.Z` tag. The tag workflow publishes image `X.Y.Z`. Existing numbered
releases must never be overwritten. `main` remains a moving development image.
Contributor and AI instructions are in [AGENTS.md](AGENTS.md).
