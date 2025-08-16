#!/usr/bin/env bash
set -euo pipefail

# If no .env file exists, copy from example
if [ ! -f /app/.env ] && [ -f /app/.env.example ]; then
  cp /app/.env.example /app/.env
fi

# Ensure writable directories exist
mkdir -p /app/storage /app/bootstrap/cache
chown -R 1000:1000 /app/storage /app/bootstrap/cache || true

# Generate APP_KEY if missing in .env
if [ -f /app/.env ]; then
  APP_KEY_LINE=$(grep -E '^APP_KEY=' /app/.env || true)
  if [ -z "${APP_KEY_LINE}" ] || [ "${APP_KEY_LINE#APP_KEY=}" = "" ]; then
    php artisan key:generate --force || true
  fi
fi

# Optionally run migrations if RUN_MIGRATIONS=true
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force || true
fi

# Use PORT env if provided
PORT_TO_USE=${PORT:-10000}

# Start Laravel development server (simple, works on Render). For production, consider php-fpm + nginx.
exec php artisan serve --host=0.0.0.0 --port="$PORT_TO_USE"
