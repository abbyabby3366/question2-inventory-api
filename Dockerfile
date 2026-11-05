# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# app: PHP-FPM running Laravel
# ---------------------------------------------------------------------------
FROM php:8.2-fpm-alpine AS app

# PHP extensions (the installer pulls the required system libraries automatically)
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql bcmath intl zip opcache pcntl redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dev dependencies are included by default so the demo seeders (Faker) and the test suite can run
# inside the container. Build with --build-arg COMPOSER_NO_DEV=1 for a production image.
ARG COMPOSER_NO_DEV=0
ENV COMPOSER_NO_DEV=${COMPOSER_NO_DEV} \
    COMPOSER_ALLOW_SUPERUSER=1

# Install dependencies first (cached layer), then copy the application.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/entrypoint.sh

ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# web: nginx serving /public and proxying PHP to the app container
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
