#!/bin/bash
set -e

cd /var/www/html

echo "[entrypoint] Running database migrations..."

attempt=1
max_attempts=10
until php artisan migrate --force; do
    if [ "$attempt" -ge "$max_attempts" ]; then
        echo "[entrypoint] Migration failed after ${max_attempts} attempts, exiting."
        exit 1
    fi
    echo "[entrypoint] Migration attempt ${attempt} failed, retrying in 2s..."
    attempt=$((attempt + 1))
    sleep 2
done

echo "[entrypoint] Caching config/routes/views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[entrypoint] Starting supervisord (php-fpm + nginx)..."
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
