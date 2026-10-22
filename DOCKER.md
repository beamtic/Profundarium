# Profundarium container

Build from the Profundarium repository root, locally or after checkout in CI:

```sh
docker build -t profundarium .
```

The image contains the application and production Composer dependencies. Composer
runs during the build using the image's PHP runtime; Composer and its download
tool remain in the build stage. No Sandboxer checkout, configuration, or mounts
are required. Local environment files, credentials, vendor files, and runtime
storage are excluded from the build context.

Use the built image in any Compose project:

```yaml
services:
  profundarium:
    image: profundarium
    read_only: true
    tmpfs:
      - /tmp:size=64m,mode=1777
    environment:
      APP_ENV: production
      APP_DEBUG: "false"
      APP_KEY: ${APP_KEY:?Set APP_KEY}
      APP_URL: ${APP_URL:?Set APP_URL}
      LOG_CHANNEL: stderr
```

This serves FastCGI on port 9000, which requires a web server configured for
Profundarium's public directory at `/srv/profundarium/public`. It does not serve
HTTP directly. The web server must have access to the public assets as well.
Provide application settings and a stable APP_KEY at runtime. No application
bind mount is needed; mounting over `/srv/profundarium` hides the bundled code
and dependencies. The image defaults to stderr logging and temporary Laravel storage under
`/tmp/profundarium-storage`. With `read_only: true`, provide the `/tmp` tmpfs
shown above for compiled views, file cache, and PHP temporary files. These files
are discarded on container recreation. Sessions are kept in memory. To persist
Laravel storage, mount a writable volume and set LARAVEL_STORAGE_PATH to its
container path instead. Do not run cache-generation Artisan commands against
the read-only `bootstrap/cache` directory at startup. The application worker account defaults to UID/GID
1000; build arguments APP_UID and APP_GID can change those IDs.

## Database selection

The same image includes PostgreSQL and SQLite PDO drivers. PostgreSQL remains
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
and checks FPM startup for pull requests targeting `main` and pushes to `main`.
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
