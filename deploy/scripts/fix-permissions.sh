#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/Manager.Shopera}"
APP_USER="${APP_USER:-www-data}"
APP_GROUP="${APP_GROUP:-www-data}"

if [ "$(id -u)" -ne 0 ]; then
    echo "Run as root: sudo APP_DIR=$APP_DIR bash deploy/scripts/fix-permissions.sh" >&2
    exit 1
fi

cd "$APP_DIR"

mkdir -p \
    bootstrap/cache \
    storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    public/build

# Only runtime paths are writable by PHP-FPM / queue workers.
chown -R "$APP_USER:$APP_GROUP" storage bootstrap/cache public/build

find storage bootstrap/cache public/build -type d -exec chmod 2775 {} \;
find storage bootstrap/cache public/build -type f -exec chmod 0664 {} \;

if [ ! -L public/storage ]; then
    if [ -e public/storage ]; then
        echo "public/storage exists but is not a symlink. Move it manually, then run: php artisan storage:link" >&2
    else
        php artisan storage:link
    fi
fi

chown -h "$APP_USER:$APP_GROUP" public/storage || true

echo "Permissions fixed for $APP_DIR"
