#!/usr/bin/env sh
set -eu

cd /var/www/html

# DB migrate/seed/import run here (not Railway pre-deploy). Wait for Laravel PDO (same as migrate).
php docker/wait-for-laravel-db.php
sleep "${AGRIGUARD_POST_TCP_SLEEP:-5}"

php artisan migrate --force
php artisan db:seed --force
php artisan historical-weather:import storage/app/public/historical_weather.csv

# Bind $PORT after DB work so healthchecks wait until migrations finished (see public/up).
cd /var/www/html/public
PORT="${PORT:-10000}"
ROUTER="/var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
exec php -S "0.0.0.0:${PORT}" "${ROUTER}"
