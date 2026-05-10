FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git unzip curl libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
    libonig-dev libxml2-dev libpq-dev \
    nodejs npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql pgsql mbstring zip exif pcntl gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader
RUN npm install && npm run build

RUN mkdir -p storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache

RUN chmod -R 775 storage bootstrap/cache

COPY docker/web-entrypoint.sh /usr/local/bin/agriguard-web
RUN chmod +x /usr/local/bin/agriguard-web

EXPOSE 10000

# JSON CMD + exec wrapper: PID 1 runs php -S directly (see docker/web-entrypoint.sh).
# Static file public/up is served by Laravel's server router before index.php — stable healthchecks.
# Migrate + seed + historical-weather import: Render (render.yaml) or Railway (railway.toml).
CMD ["/usr/local/bin/agriguard-web"]
