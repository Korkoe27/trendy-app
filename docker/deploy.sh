#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html || exit 1

# Ensure .env exists
if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
fi

# Ensure storage directories and permissions
mkdir -p storage app/storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true

# Generate APP_KEY if missing
if [ -f .env ]; then
  APP_KEY_LINE=$(grep -E '^APP_KEY=' .env || true)
  if [ -z "${APP_KEY_LINE}" ] || [ "${APP_KEY_LINE#APP_KEY=}" = "" ]; then
    echo "Generating APP_KEY"
    php artisan key:generate --force || true
  fi
fi

# Create storage symlink if missing
if [ ! -L public/storage ]; then
  php artisan storage:link || true
fi

# Clear caches to pick up runtime env
php artisan optimize:clear || true

# Optionally run migrations
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  echo "Running migrations"
  php artisan migrate --force || true
fi

echo "Deploy script finished"
