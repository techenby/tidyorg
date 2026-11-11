# syntax=docker/dockerfile:1

# PHP dependencies, with the first-party modules copied in from modules/.
FROM composer:2 AS vendor

WORKDIR /app

ENV COMPOSER_MIRROR_PATH_REPOS=1

COPY composer.json composer.lock ./
COPY modules ./modules

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs

# Front-end assets, built here so the final image and its users never need Node.
FROM node:22-slim AS assets

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY modules ./modules
COPY --from=vendor /app/vendor ./vendor

RUN npm run build

# The image that runs: one image for the web server, the queue worker, and the scheduler.
FROM dunglas/frankenphp:1-php8.5

RUN install-php-extensions \
    bcmath \
    exif \
    gd \
    intl \
    opcache \
    pcntl \
    pdo_mysql \
    pdo_pgsql \
    zip

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini "$PHP_INI_DIR/conf.d/tessera.ini"
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/tessera

WORKDIR /app

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# Run as an unprivileged user that owns only what the app writes to.
RUN useradd --system --no-create-home tessera \
    && chmod +x /usr/local/bin/tessera \
    && mkdir -p \
        bootstrap/cache \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && php artisan package:discover --ansi \
    && chown -R tessera:tessera storage bootstrap/cache database /data /config

USER tessera

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

EXPOSE 8080

ENTRYPOINT ["tessera"]
CMD ["web"]
