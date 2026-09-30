# syntax=docker/dockerfile:1.7
#
# Multi-stage build. The final images contain no build tools, no dev
# dependencies, no Node.js and no tests:
#
#   vendor  -> production Composer dependencies
#   assets  -> Vite build (needs Ziggy from vendor/)
#   app     -> PHP-FPM runtime (also runs the queue worker and scheduler)
#   web     -> Nginx serving public/ and proxying PHP to `app`

############################################################
# 1. PHP dependencies
############################################################
FROM composer:2 AS vendor

WORKDIR /app

# Dependencies first: this layer is cached until composer.lock changes.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev --no-scripts --no-autoloader \
        --prefer-dist --no-interaction --no-progress \
        # Extensions are installed in the runtime stage, not in this image.
        --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

############################################################
# 2. Front-end assets
############################################################
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.ts tsconfig.json tailwind.config.js components.json ./
COPY resources ./resources
COPY --from=vendor /app/vendor/tightenco/ziggy ./vendor/tightenco/ziggy
RUN npm run build

############################################################
# 3. Runtime: PHP-FPM
############################################################
FROM php:8.3-fpm-alpine AS app

# install-php-extensions resolves build dependencies and removes them
# afterwards, keeping the image small.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql intl zip gd bcmath opcache pcntl redis \
    && apk add --no-cache fcgi \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/app/public storage/app/private storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

# Package discovery was skipped with --no-scripts (dev packages are absent).
RUN php artisan package:discover --ansi

EXPOSE 9000

# The first start migrates (and may seed the demo) before FPM listens.
HEALTHCHECK --interval=10s --timeout=3s --start-period=180s --retries=5 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

############################################################
# 4. Web server: Nginx
############################################################
FROM nginx:1.31-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/security-headers.conf /etc/nginx/snippets/security-headers.conf
COPY --from=app /var/www/html/public /var/www/html/public

HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=5 \
    CMD wget -qO- http://127.0.0.1/up > /dev/null || exit 1
