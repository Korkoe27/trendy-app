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
FROM php:8.2-cli-alpine
WORKDIR /app

# system deps
RUN apk add --no-cache bash icu-dev libzip-dev zlib-dev curl oniguruma-dev

# php extensions
RUN docker-php-ext-install pdo pdo_mysql zip intl

# Copy application from composer build stage
COPY --from=composer_builder /app /app

# Copy built frontend assets
COPY --from=node_builder /app/public/build /app/public/build

# Ensure storage and cache dirs exist
RUN mkdir -p /app/storage /app/bootstrap/cache || true
RUN chown -R 1000:1000 /app/storage /app/bootstrap/cache || true

ENV APP_ENV=production
ENV HOST=0.0.0.0
ENV PORT=10000

EXPOSE 10000

# Add entrypoint to handle runtime boot tasks
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
