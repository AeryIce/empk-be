# --- build vendor (composer) ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# --- runtime: FrankenPHP (Caddy + PHP 8.3) ---
FROM ghcr.io/dunglas/frankenphp:1-php8.3

# pastikan pdo_pgsql ada
RUN install-php-extensions pdo_pgsql

WORKDIR /app
COPY . .
COPY --from=vendor /app/vendor ./vendor

# permissions laravel
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# caddy config
COPY ./deploy/Caddyfile /etc/caddy/Caddyfile

# jangan cache config di build-time (ENV belum ada)
RUN php artisan optimize:clear || true

# Railway memberikan $PORT otomatis
ENV SERVER_NAME=:${PORT}
EXPOSE 8080
