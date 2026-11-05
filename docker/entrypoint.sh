#!/bin/sh
set -e

# Create .env with an application key on first start (real environment variables from
# docker-compose still take precedence over values in .env).
if [ ! -f .env ]; then
    cp .env.example .env
fi
if ! grep -q '^APP_KEY=base64:' .env && [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

php artisan migrate --force

if [ "$SEED_DATABASE" = "true" ]; then
    php artisan db:seed --force
fi

php artisan config:cache
php artisan route:cache

exec "$@"

