#!/bin/sh
set -e
php artisan optimize --quiet
chown -R www-data:www-data bootstrap/cache storage/framework 2>/dev/null || true

exec "$@"
