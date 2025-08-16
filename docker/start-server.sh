#!/usr/bin/env bash
set -euo pipefail

# Ensure php-fpm socket location
PHP_FPM_SOCK=${PHP_FPM_SOCK:-/var/run/php-fpm.sock}

# Start php-fpm in background
php-fpm -F -R &

# Start nginx in foreground
nginx -g 'daemon off;'
