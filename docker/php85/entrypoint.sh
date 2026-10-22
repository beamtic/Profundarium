#!/bin/sh
set -eu

if [ "${1:-}" = php-fpm85 ]; then
    # A tmpfs starts empty, so prepare Laravel storage before workers start.
    storage=${LARAVEL_STORAGE_PATH:-/tmp/profundarium-storage}
    mkdir -p "$storage/framework/cache/data" "$storage/framework/sessions" \
        "$storage/framework/views" "$storage/logs"
    chown -R profundarium:profundarium "$storage"
fi

exec "$@"
