# Multi-stage Dockerfile for building assets and PHP app

#########################
# Node build stage
#########################
FROM node:18-alpine AS node_builder
WORKDIR /app
COPY package*.json ./
COPY vite.config.js ./
COPY resources ./resources
RUN npm ci --silent
RUN npm run build --silent

#########################
# Composer / PHP build stage
#########################
FROM composer:2 AS composer_builder
WORKDIR /app
COPY composer.json composer.lock ./
# Install composer dependencies (ignore platform requirements to keep build simple)
RUN composer install --no-dev --no-scripts --prefer-dist --no-progress --no-interaction --ignore-platform-reqs
COPY . /app
RUN composer dump-autoload --optimize

#########################
# Final runtime image
#########################
FROM php:8.2-fpm-alpine AS runtime
WORKDIR /var/www/html

# system deps
RUN apk add --no-cache bash icu-dev libzip-dev zlib-dev curl oniguruma-dev nginx postgresql-dev

# php extensions
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql zip intl

# Copy application from composer build stage
COPY --from=composer_builder /app /var/www/html

# Copy built frontend assets
COPY --from=node_builder /app/public/build /var/www/html/public/build

# Ensure storage and cache dirs exist and correct ownership
RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache /run/nginx \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

ENV APP_ENV=production
ENV PORT=10000

# Nginx listens on PORT 10000 (configured via default.conf)
EXPOSE 10000

# Copy nginx config and startup scripts
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/default.conf /etc/nginx/conf.d/default.conf
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
COPY docker/deploy.sh /usr/local/bin/deploy.sh
COPY docker/start-server.sh /usr/local/bin/start-server.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh /usr/local/bin/start-server.sh
RUN chmod +x /usr/local/bin/deploy.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
