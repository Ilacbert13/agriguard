#!/usr/bin/env sh
set -eu
cd /var/www/html
npm ci
npm run build
