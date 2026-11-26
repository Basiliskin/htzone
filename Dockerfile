# syntax=docker/dockerfile:1
# One recipe, three stages (D26):
#   base → shared PHP + Apache setup, non-root on port 8080
#   dev  → + Composer + Xdebug (off by default, D27); the project is mounted at /app by compose
#   prod → code + production dependencies baked in, OPcache tuned (default target, used by Render)

FROM php:8.3.37-apache-trixie AS base
ENV APP_ENV=prod \
    DB_PATH=/app/var/catalog.sqlite \
    LOG_LEVEL=info \
    PORT=8080
WORKDIR /app
# Apache: serve /app/public, listen on an unprivileged port so it can run as www-data (non-root).
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite headers \
 && sed -ri 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
 && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf && a2enconf servername \
 && sed -ri 's/^ServerTokens .*/ServerTokens Prod/; s/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf \
 && chown -R www-data:www-data /var/run/apache2 /var/lock/apache2 /var/log/apache2 \
 && mkdir -p /app/var && chown www-data:www-data /app/var
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
EXPOSE 8080

FROM base AS dev
ENV APP_ENV=dev \
    COMPOSER_HOME=/tmp/composer \
    XDEBUG_MODE=off
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends unzip git && rm -rf /var/lib/apt/lists/* \
 && pecl install xdebug-3.4.7 && docker-php-ext-enable xdebug
COPY docker/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini
COPY docker/php-dev.ini /usr/local/etc/php/conf.d/zz-dev.ini
USER www-data

FROM base AS vendor
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --no-autoloader
# The classmap must see our own classes, so build the autoloader after src/ is in place
COPY src ./src
RUN composer dump-autoload --no-dev --classmap-authoritative

FROM base AS prod
COPY docker/opcache-prod.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY --from=vendor /app/vendor ./vendor
COPY src ./src
COPY public ./public
COPY templates ./templates
COPY bin ./bin
COPY migrations ./migrations
COPY data/enrichment.jsonl data/price-ranges.json ./data/
COPY assets/catalog_no_prices.csv ./assets/
# Build the catalog DB inside the image (D5, ADR 0002): the deploy needs no external database.
# Then make code and DB read-only; at runtime the app opens the DB with SQLITE_OPEN_READONLY too (AC-6.4).
RUN php bin/import \
 && chown -R root:root /app \
 && chmod -R a-w /app \
 && chmod 0444 /app/var/catalog.sqlite
USER www-data
