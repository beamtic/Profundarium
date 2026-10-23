#!/bin/sh
set -eu
response=$(env -i SCRIPT_NAME=/fpm-ping SCRIPT_FILENAME=/fpm-ping \
    REQUEST_METHOD=GET /usr/bin/cgi-fcgi -bind -connect 127.0.0.1:9000)
printf '%s\n' "$response" | grep -qx 'pong'
