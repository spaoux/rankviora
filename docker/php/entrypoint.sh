#!/bin/sh
set -eu
if [ ! -f vendor/autoload.php ]; then composer install --no-interaction --prefer-dist; fi
if [ ! -f .env ]; then cp .env.example .env; fi
if ! grep -q '^APP_KEY=base64:' .env; then php artisan key:generate --force; fi
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
php artisan migrate --force
exec "$@"
