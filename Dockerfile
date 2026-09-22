# syntax=docker/dockerfile:1

########################################
# Stage 1: base — PHP 8.3 runtime + system deps + extensions
# (shared by the composer stage and the production stage so
#  `composer install`'s platform check sees the real extension set)
########################################
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
        nginx \
        supervisor \
        bash \
        curl \
        git \
        unzip \
    # install-php-extensions (mlocati/docker-php-extension-installer) handles
    # build deps, phpize/config.m4 setup and cleanup ordering itself — chaining
    # extensions by hand via `docker-php-ext-install` on this base image
    # corrupted the shared build tree partway through (intl/opcache failing
    # with "Cannot find config.m4"), even with -j(nproc) dropped and opcache
    # split into its own call. This script is the standard workaround.
    && curl -sSLf -o /usr/local/bin/install-php-extensions \
        https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions \
        pdo_pgsql \
        pgsql \
        pdo_sqlite \
        sqlite3 \
        zip \
        opcache \
        bcmath \
        intl

########################################
# Stage 2: vendor — composer install (production deps only)
########################################
FROM base AS vendor

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

########################################
# Stage 3: production — final runtime image (php-fpm + nginx via supervisor)
########################################
FROM base AS production

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Dummy build-time-only values so `artisan package:discover` can boot the
# framework during the image build. Overwritten by real env vars at runtime.
ENV APP_KEY=base64:bvA1yn2NW+mTFBUN/P3fSBkIenplqpLmsT8KpDxkvCE= \
    APP_URL=http://localhost

RUN composer dump-autoload --optimize --no-scripts \
    && php artisan package:discover --ansi

# nginx (replaces the stock main config entirely: worker_processes must live
# at the top level, not inside a conf.d server-block include)
COPY docker/nginx.conf /etc/nginx/nginx.conf

# php-fpm pool tuning (replaces the default www.conf pool)
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf

# opcache
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=10000'; \
    } > /usr/local/etc/php/conf.d/opcache-custom.ini

# supervisor: exactly two programs — php-fpm and nginx. No queue worker,
# no scheduler loop, no Horizon, no Reverb.
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
# rebuilt with railway CLI 5.59.0 20260922T112440
