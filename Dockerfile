# Base: PHP CLI 8.3 dari Docker Hub
FROM php:8.3-cli

# Lib OS untuk ekstensi yang dibutuhkan Laravel/Filament
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libpq-dev zlib1g-dev libzip-dev libicu-dev \
 && docker-php-ext-configure intl \
 && docker-php-ext-install -j$(nproc) intl zip pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

# 1) Copy composer files dulu (biar cache efisien)
COPY composer.json composer.lock ./

# 2) Install vendor TANPA scripts (belum ada artisan di tahap ini)
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader

# 3) Baru copy seluruh source
COPY . .

# Permission dasar Laravel
RUN mkdir -p storage bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

# (opsional, toleran tanpa .env di build-time)
RUN php artisan optimize:clear || true

# Railway expose via PORT (default 8080)
ENV PORT=8080
EXPOSE 8080

# PHP built-in server untuk demo (serve folder public)
CMD [ "sh", "-c", "php -S 0.0.0.0:${PORT} -t public server.php" ]
