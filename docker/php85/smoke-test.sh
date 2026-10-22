#!/bin/sh
set -eu

/bin/sh /usr/local/bin/php-fpm-entrypoint profundarium &
supervisor_pid=$!
trap 'kill "$supervisor_pid"; wait "$supervisor_pid"' EXIT
ready=0
for attempt in 1 2 3 4 5 6 7 8 9 10; do
    if /usr/local/bin/profundarium-healthcheck; then ready=1; break; fi
    sleep 1
done
test "$ready" = 1
wget -q -O /tmp/profundarium-response http://127.0.0.1:8080/profundarium/
grep -qi '<html' /tmp/profundarium-response
wget -q -O /dev/null http://127.0.0.1:8080/profundarium/assets/css/notes.css
# Never serve private application files.
if wget -q -O /dev/null http://127.0.0.1:8080/.env; then exit 1; fi
if wget -q -O /dev/null http://127.0.0.1:8080/composer.json; then exit 1; fi

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
