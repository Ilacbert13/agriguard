FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git unzip curl libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
    libonig-dev libxml2-dev libpq-dev \
    python3 python3-pip python3-venv \
    nodejs npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql pgsql mbstring zip exif pcntl gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

# Installs all `require` packages from composer.lock (includes resend/resend-laravel for MAIL_MAILER=resend).
RUN composer install --no-dev --optimize-autoloader

# ML weather prediction (WeatherPredictionService → python/predict.py + bundled .pkl model).
RUN python3 -m venv .venv \
    && .venv/bin/pip install --no-cache-dir --upgrade pip \
    && .venv/bin/pip install --no-cache-dir -r python/requirements.txt

# Frontend build runs at container start (docker/vite-build.sh), not here — keeps image smaller and matches Railway startCommand.

RUN mkdir -p storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache

RUN chmod -R 775 storage bootstrap/cache

COPY docker/vite-build.sh /usr/local/bin/vite-build
COPY docker/agriguard-start.sh /usr/local/bin/agriguard-start
RUN chmod +x /usr/local/bin/vite-build /usr/local/bin/agriguard-start

EXPOSE 10000

# PID 1: vite-build → agriguard-db-setup → php built-in server on public/ (see docker/agriguard-start.sh).
CMD ["/usr/local/bin/agriguard-start"]
