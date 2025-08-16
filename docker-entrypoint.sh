#!/usr/bin/env bash
set -euo pipefail

# If no .env file exists, copy from example
if [ ! -f /var/www/html/.env ] && [ -f /var/www/html/.env.example ]; then
  cp /var/www/html/.env.example /var/www/html/.env
fi

# Ensure writable directories exist
mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

# Generate APP_KEY if missing in .env
if [ -f /var/www/html/.env ]; then
  APP_KEY_LINE=$(grep -E '^APP_KEY=' /var/www/html/.env || true)
  if [ -z "${APP_KEY_LINE}" ] || [ "${APP_KEY_LINE#APP_KEY=}" = "" ]; then
    php artisan key:generate --force || true
  fi
fi

# Optionally run migrations if RUN_MIGRATIONS=true
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force || true
fi

# Clear config so runtime env vars are used
php artisan config:clear || true

# Run deploy tasks
if [ -x /usr/local/bin/deploy.sh ]; then
  /usr/local/bin/deploy.sh || true
fi

# Start php-fpm and nginx
exec /usr/local/bin/start-server.sh
