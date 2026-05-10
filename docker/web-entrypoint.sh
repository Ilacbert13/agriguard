#!/usr/bin/env sh
set -eu
PORT="${PORT:-10000}"
exec php artisan serve --host=0.0.0.0 --port="$PORT"
