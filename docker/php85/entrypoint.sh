#!/bin/sh
set -eu

if [ "${1:-}" = profundarium ]; then
    storage=${LARAVEL_STORAGE_PATH:-/tmp/profundarium-storage}
    mkdir -p "$storage/framework/cache/data" "$storage/framework/sessions" \
        "$storage/framework/views" "$storage/logs" /tmp/nginx
    chown -R profundarium:profundarium "$storage" /tmp/nginx
    mkdir -p /tmp/profundarium-services
    cp -Rs /etc/profundarium/services/. /tmp/profundarium-services/
    php-fpm85 -t
    nginx -e stderr -t
    exec s6-svscan /tmp/profundarium-services
fi

exec "$@"
