# Base: PHP CLI 8.3 dari Docker Hub (stabil & publik)
FROM php:8.3-cli

# Dependensi OS untuk ekstensi PHP yang dibutuhkan
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libpq-dev zlib1g-dev libzip-dev libicu-dev \
 && docker-php-ext-configure intl \
 && docker-php-ext-install -j$(nproc) intl zip pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*

# Tambah composer dari image resmi
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy hanya composer files dulu biar cache efisien
COPY composer.json composer.lock ./

# Install vendor (prod)
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# Copy seluruh source
COPY . .

# Permission dasar Laravel
RUN mkdir -p storage bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

# (Opsional) bersih-bersih cache saat build (toleran jika .env belum ada)
RUN php artisan optimize:clear || true

# Railway pakai env PORT, default ke 8080 bila tidak ada
ENV PORT=8080

EXPOSE 8080

# Jalankan PHP built-in server (cukup untuk demo), serve folder "public"
CMD [ "sh", "-c", "php -S 0.0.0.0:${PORT} -t public" ]
