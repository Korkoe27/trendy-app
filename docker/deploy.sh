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

 # Generate APP_KEY if missing and persist it into .env
if [ -f .env ]; then
  APP_KEY_LINE=$(grep -E '^APP_KEY=' .env || true)
  if [ -z "${APP_KEY_LINE}" ] || [ "${APP_KEY_LINE#APP_KEY=}" = "" ]; then
    echo "Generating APP_KEY"
    GENERATED_KEY=$(php artisan key:generate --show || true)
    if [ -n "${GENERATED_KEY}" ]; then
      # Remove existing APP_KEY lines and append the new one
      sed -i "/^APP_KEY=/d" .env || true
      echo "APP_KEY=${GENERATED_KEY}" >> .env
    fi
  fi
fi

# Create storage symlink if missing
if [ ! -L public/storage ]; then
  php artisan storage:link || true
fi

 # Optionally run migrations
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  echo "Running migrations"
  # Run migrations but tolerate missing DB driver error
  php artisan migrate --force || true
fi

# Clear caches to pick up runtime env and migrated schema
# Some cache operations may attempt DB access; tolerate failures
php artisan optimize:clear || true

echo "Deploy script finished"
