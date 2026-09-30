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
#
# PHP_CLI_SERVER_WORKERS spawns multiple worker processes (needs pcntl,
# installed above) so the API stays responsive under normal traffic.
# Renders are now queued (App\Jobs\RenderVideoJob) instead of running
# inline in the HTTP request, so 2 is plenty — the actual FFmpeg work
# happens in the separate queue:work process below, which naturally
# processes one render at a time (exactly the safety limit a thin
# free-tier CPU/RAM box needs). The restart loop keeps the worker alive
# if a render ever crashes it.
ENV PHP_CLI_SERVER_WORKERS=2
CMD ["sh", "-c", "php artisan migrate --force || true; php artisan storage:link || true; (while true; do php artisan queue:work --tries=1 --timeout=280 --sleep=3; sleep 2; done) & php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"]
