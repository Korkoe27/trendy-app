#!/usr/bin/env bash
set -euo pipefail

# Ports/paths
PORT="${PORT:-10000}"                        # Render exposes this
PHP_FPM_CONF="${PHP_FPM_CONF:-/usr/local/etc/php-fpm.conf}"
PHP_FPM_BIN="${PHP_FPM_BIN:-php-fpm}"
NGINX_BIN="${NGINX_BIN:-nginx}"

# If you ship a templated nginx conf, render it with env (e.g., $PORT)
if [ -f /etc/nginx/conf.d/default.conf.template ]; then
  envsubst '$PORT' < /etc/nginx/conf.d/default.conf.template > /etc/nginx/conf.d/default.conf
fi

# Make sure runtime dirs exist
mkdir -p /run/nginx /run/php
chown -R www-data:www-data /run/nginx /run/php || true

pids=()

# Forward SIGTERM/SIGINT to children, wait for them, and exit if either dies
term_handler() {
  echo "Shutting down services..."
  if [ -n "${pids[0]:-}" ] && kill -0 "${pids[0]}" 2>/dev/null; then kill "${pids[0]}"; fi
  if [ -n "${pids[1]:-}" ] && kill -0 "${pids[1]}" 2>/dev/null; then kill "${pids[1]}"; fi
  wait
  exit 143
}
trap term_handler TERM INT

# --- Start php-fpm (foreground) ---
# NOTE: don't use -R (keeps master as root). php-fpm will drop workers to www-data per conf.
"$PHP_FPM_BIN" -F -y "$PHP_FPM_CONF" &
pids[0]=$!

# --- Start nginx (foreground) ---
# Expect your /etc/nginx/conf.d/default.conf to 'listen 10000;'
$NGINX_BIN -g 'daemon off;' &
pids[1]=$!

# --- Wait for either to exit; if one dies, exit non-zero to restart container ---
wait -n "${pids[0]}" "${pids[1]}"
status=$?

echo "One of the processes exited (status=$status); stopping the other..."
for pid in "${pids[@]}"; do
  if [ -n "${pid:-}" ] && kill -0 "$pid" 2>/dev/null; then kill "$pid" || true; fi
done
wait || true
exit $status
