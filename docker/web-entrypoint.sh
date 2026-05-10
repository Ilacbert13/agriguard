#!/usr/bin/env sh
set -eu

# Bind $PORT (Railway/Render inject this). Do not use `php artisan serve` here: it wraps a
# child `php -S` process, which complicates signals and can delay bind — bad for healthchecks.
# CWD must be /public: Laravel's server.php resolves static files (e.g. /up) from getcwd().
cd /var/www/html/public

PORT="${PORT:-10000}"
ROUTER="/var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"

exec php -S "0.0.0.0:${PORT}" "${ROUTER}"
