# syntax=docker/dockerfile:1
#
# Build context: repository root (see .dockerignore). Stages:
#   base   -> runtime shared by dev and qa (same layers, same behaviour)
#   dev    -> day-to-day image: code arrives through a bind mount
#   vendor -> disposable: production composer dependencies
#   assets -> disposable: compiled Vite bundle
#   qa     -> immutable image: code + vendor + assets baked in, no dev tooling

# ===========================================================================
# base
# ===========================================================================
FROM php:8.4-apache-bookworm AS base

ARG PUID=1000
ARG PGID=1000
ARG APP_USER=workspace
ARG TZ=UTC

ENV APP_USER=${APP_USER} \
    APACHE_RUN_USER=${APP_USER} \
    APACHE_RUN_GROUP=${APP_USER} \
    TZ=${TZ} \
    COMPOSER_HOME=/tmp/composer \
    DOCKER_SUBNET=172.29.0.0/24

# Runtime packages only. curl is used by the HEALTHCHECK.
# -dev headers stay installed on purpose: purging them can drag the runtime
# library (libpq5, libicu72) along and leave the extension unable to load.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        curl \
        libpq-dev libicu-dev libzip-dev libgmp-dev; \
    rm -rf /var/lib/apt/lists/*

# PHP extensions. Each one has a reason; nothing is installed "just in case":
#   pdo_pgsql -> PostgreSQL (central + one database per tenant)
#   intl      -> Laravel localisation / number & date formatting
#   bcmath    -> exact arithmetic without float drift
#   zip       -> composer dist installs and exports
#   opcache   -> realistic performance
#   pcntl     -> graceful SIGTERM in queue:work / schedule:work
#   redis     -> phpredis client (REDIS_CLIENT=phpredis)
#   gmp       -> stellar-php-sdk: sealing on Stellar (it. 13)
# It. 49: pecl.php.net sometimes does not answer (in CI, several machines build
# at once), so the download gets three attempts before the build fails.
RUN set -eux; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql intl bcmath zip opcache pcntl gmp; \
    for attempt in 1 2 3; do pecl install redis-6.2.0 && break; [ "$attempt" -lt 3 ]; sleep 10; done; \
    docker-php-ext-enable redis

RUN ln -snf "/usr/share/zoneinfo/${TZ}" /etc/localtime && echo "${TZ}" > /etc/timezone

# Apache.
#   000-default is disabled: it points at /var/www/html and would serve the
#   SOURCE CODE, .env included.
#   ServerTokens/ServerSignature: do not advertise versions.
RUN set -eux; \
    a2enmod rewrite headers remoteip; \
    a2dissite 000-default; \
    sed -ri 's/^ServerTokens .*/ServerTokens Prod/; s/^ServerSignature .*/ServerSignature Off/' \
        /etc/apache2/conf-available/security.conf
COPY docker/app/mpm_prefork.conf /etc/apache2/mods-available/mpm_prefork.conf
COPY docker/app/vhost.conf /etc/apache2/sites-available/govtrace.conf
RUN a2ensite govtrace

# Unprivileged user with the host UID/GID: files created in the bind mount
# belong to the developer, never to root.
RUN set -eux; \
    if getent group "${PGID}" >/dev/null; then \
        groupmod -n "${APP_USER}" "$(getent group "${PGID}" | cut -d: -f1)"; \
    else \
        groupadd -g "${PGID}" "${APP_USER}"; \
    fi; \
    if getent passwd "${PUID}" >/dev/null; then \
        usermod -l "${APP_USER}" -d "/home/${APP_USER}" -m \
                "$(getent passwd "${PUID}" | cut -d: -f1)"; \
    else \
        useradd -u "${PUID}" -g "${PGID}" -m -s /bin/bash "${APP_USER}"; \
    fi; \
    install -d -o "${APP_USER}" -g "${APP_USER}" /tmp/composer

WORKDIR /var/www/html

COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Static file on purpose: checks Apache and the DocumentRoot without depending
# on PHP, vendor/, the database or tenant routing. /up is the app-level check.
HEALTHCHECK --interval=15s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS -o /dev/null http://127.0.0.1/robots.txt || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]

# ===========================================================================
# dev
# ===========================================================================
FROM base AS dev

# git/unzip for composer; psql for manual diagnostics from the container.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip postgresql-client; \
    rm -rf /var/lib/apt/lists/*

# Installed but NOT enabled: `make xdebug-on` mounts the ini that loads it.
RUN pecl install xdebug-3.4.5
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/app/php-dev.ini /usr/local/etc/php/conf.d/zz-custom.ini

# ===========================================================================
# vendor (disposable)
# ===========================================================================
FROM base AS vendor

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip; \
    rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction

# ===========================================================================
# assets (disposable)
# ===========================================================================
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
# El validador del navegador usa la misma biblioteca que el verificador
# independiente (it. 27): sin ella, la imagen inmutable no compilaba (it. 42a).
COPY tools/verify/lib ./tools/verify/lib
COPY --from=vendor /var/www/html/vendor/laravel/framework/src/Illuminate/Pagination/resources/views \
     ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

# ===========================================================================
# qa (immutable)
# ===========================================================================
FROM base AS qa

COPY docker/app/php-prod.ini /usr/local/etc/php/conf.d/zz-custom.ini
COPY --from=vendor --chown=workspace:workspace /var/www/html /var/www/html
COPY --from=assets --chown=workspace:workspace /app/public/build /var/www/html/public/build
# It. 42a: storage/logs vive en un volumen (docker-compose.prod.yml), y un volumen
# nuevo copia el dueño de lo que la imagen tiene en ese lugar: el de la aplicación.
RUN install -d -o workspace -g workspace -m 775 /var/www/html/storage/logs
