#!/usr/bin/env sh
set -eu

cd /var/www/html

# MySQL may not listen yet when Railway/Render starts pre-deploy (connection refused).
php docker/wait-for-db-tcp.php
# Brief pause after TCP accept — server sometimes needs a moment before auth/migrate.
sleep "${AGRIGUARD_POST_TCP_SLEEP:-5}"

php artisan migrate --force
php artisan db:seed --force
php artisan historical-weather:import storage/app/public/historical_weather.csv
