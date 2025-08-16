# ===================================
# 1. Node build stage (Vite frontend)
# ===================================
FROM node:18-alpine AS node_builder
WORKDIR /app
COPY package*.json ./
COPY vite.config.js ./
COPY resources ./resources
RUN npm ci --silent
RUN npm run build --silent

# ===================================
# 2. Composer / PHP build stage
# ===================================
FROM composer:2 AS composer_builder
WORKDIR /app
COPY composer.json composer.lock ./
# Install composer dependencies
RUN composer install --no-dev --no-scripts --prefer-dist --no-progress --no-interaction --ignore-platform-reqs
COPY . /app
RUN composer dump-autoload --optimize

# ===================================
# 3. Final runtime image (Nginx + PHP-FPM)
# ===================================
FROM php:8.2-fpm-alpine AS runtime
WORKDIR /var/www/html

# Install system deps
RUN apk add --no-cache \
    bash \
    icu-dev \
    libzip-dev \
    zlib-dev \
    curl \
    oniguruma-dev \
    nginx \
    postgresql-dev \
    git \
    supervisor

# PHP extensions
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql zip intl opcache

# Copy app from composer build stage
COPY --from=composer_builder /app /var/www/html

# Copy built frontend assets
COPY --from=node_builder /app/public/build /var/www/html/public/build

# Ensure storage + cache dirs exist and are writable
RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache /run/nginx \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

# Environment defaults
ENV APP_ENV=production
ENV PORT=10000

# Expose Render port
EXPOSE 10000

# Copy configs and scripts
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/default.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
COPY docker/deploy.sh /usr/local/bin/deploy.sh
COPY docker/start-server.sh /usr/local/bin/start-server.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/deploy.sh \
    && chmod +x /usr/local/bin/start-server.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
