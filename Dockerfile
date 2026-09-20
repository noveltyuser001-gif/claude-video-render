FROM php:8.2-cli

# FFmpeg (the actual video renderer) + build deps for the PHP extensions
# Laravel/this app needs.
RUN apt-get update && apt-get install -y --no-install-recommends \
        ffmpeg \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libpq-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql mbstring zip exif pcntl bcmath gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views \
        storage/app/private/videos storage/app/public/generated storage/app/public/uploads storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8080

# Render sets $PORT at runtime; migrate is safe to re-run (Laravel skips
# already-applied migrations), and storage:link may already exist on a
# re-deploy of the same instance.
CMD ["sh", "-c", "php artisan migrate --force || true; php artisan storage:link || true; php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"]
