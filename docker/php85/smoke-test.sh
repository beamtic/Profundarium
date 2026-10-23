#!/bin/sh
set -eu

php-fpm85 -t
/bin/sh /usr/local/bin/php-fpm-entrypoint php-fpm85 -F &
fpm_pid=$!
trap 'kill "$fpm_pid"' EXIT
ready=0
for attempt in 1 2 3 4 5; do
    if /usr/local/bin/php-fpm-healthcheck; then ready=1; break; fi
    sleep 1
done
test "$ready" = 1

# Render a real Blade page through FPM as its non-root application worker.
env -i SCRIPT_NAME=/index.php SCRIPT_FILENAME=/srv/profundarium/public/index.php \
    REQUEST_METHOD=GET REQUEST_URI=/profundarium SERVER_NAME=localhost \
    SERVER_PORT=80 SERVER_PROTOCOL=HTTP/1.1 HTTP_HOST=localhost \
    /usr/bin/cgi-fcgi -bind -connect 127.0.0.1:9000 > /tmp/profundarium-response
if grep -q '^Status:' /tmp/profundarium-response; then
    cat /tmp/profundarium-response
    exit 1
fi
grep -qi '<html' /tmp/profundarium-response

# Exercise default logging without an application .env file.
php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config("logging.default") !== "stderr") { exit(1); }
Illuminate\Support\Facades\Log::warning("Read-only container logging check");
Illuminate\Support\Facades\Cache::put("read-only-check", "ok", 60);
if (Illuminate\Support\Facades\Cache::get("read-only-check") !== "ok") { exit(2); }
'
test -f vendor/autoload.php
test ! -f .env
test ! -e /usr/local/bin/composer
test ! -f "$LARAVEL_STORAGE_PATH/logs/laravel.log"
