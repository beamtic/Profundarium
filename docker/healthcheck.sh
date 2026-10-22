#!/bin/sh
set -eu
# HTTP health route checks Nginx, FastCGI, and Laravel together.
exec wget -q -O /dev/null http://127.0.0.1:8080/up
