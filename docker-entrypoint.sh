#!/usr/bin/env bash
set -euo pipefail

log()  { echo "[$(date -u +'%Y-%m-%dT%H:%M:%SZ')] $*"; }
warn() { echo "[$(date -u +'%Y-%m-%dT%H:%M:%SZ')] WARN: $*" >&2; }
err()  { echo "[$(date -u +'%Y-%m-%dT%H:%M:%SZ')] ERROR: $*" >&2; }

# --- 1) Sanity checks (no .env writes in containers) ---
if [ -z "${APP_KEY:-}" ]; then
  err "APP_KEY is not set. Set it in Render's Environment (e.g., base64:...)."
  exit 1
fi

# --- 2) Ensure writable dirs ---
mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

# --- 3) One-time niceties (never fatal) ---
php artisan storage:link || true

# Build lightweight runtime caches (do NOT clear DB cache here)
php artisan config:clear || true
php artisan config:cache || true
php artisan route:cache  || true
php artisan view:cache   || true

# --- 4) Optional migrations with retries (no .env mutation) ---
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  log "RUN_MIGRATIONS=true — attempting database migrations..."
  tries=0
  until php artisan migrate --force; do
    tries=$((tries+1))
    if [ "$tries" -ge 5 ]; then
      warn "Migrations still failing after ${tries} attempts; continuing startup."
      break
    fi
    warn "Migration attempt ${tries} failed; retrying in 5s..."
    sleep 5
  done
fi

# --- 5) Optional deploy hook (safe if missing) ---
if [ -x /usr/local/bin/deploy.sh ]; then
  log "Running deploy.sh..."
  /usr/local/bin/deploy.sh || warn "deploy.sh exited non-zero; continuing."
fi

# --- 6) Start services ---
exec /usr/local/bin/start-server.sh
