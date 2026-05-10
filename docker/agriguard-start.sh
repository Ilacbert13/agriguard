#!/usr/bin/env sh
set -eu

cd /var/www/html

# Single PHP process: wait + migrate + seed + CSV (see docker/agriguard-db-setup.php).
php docker/agriguard-db-setup.php

# Bind $PORT after DB work so healthchecks wait until migrations finished (see public/up).
cd /var/www/html/public
PORT="${PORT:-10000}"
ROUTER="/var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
exec php -S "0.0.0.0:${PORT}" "${ROUTER}"
