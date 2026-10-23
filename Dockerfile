FROM alpine:3.24.2 AS runtime

# Fixed application account, independent of host bind mounts.
ARG APP_UID=1000
ARG APP_GID=1000

RUN apk add --no-cache \
    "php85>=8.5.12" \
    "php85-fpm>=8.5.12" \
    fcgi

# PHP extensions used by Laravel / Profundarium in production.
# OPcache is built into PHP 8.5.
RUN apk add --no-cache \
    php85-session \
    php85-posix \
    php85-ctype \
    php85-fileinfo \
    php85-dom \
    php85-xml \
    php85-sodium \
    php85-simplexml \
    php85-curl \
    php85-xmlwriter \
    php85-xmlreader \
    php85-mbstring \
    php85-phar \
    php85-openssl \
    php85-tokenizer \
    php85-zip \
    php85-pcntl \
    php85-bcmath \
    php85-pdo_mysql \
    php85-pdo_pgsql \
    php85-pdo_sqlite

COPY ./docker/php85/php-fpm.conf /etc/php85/php-fpm.conf
COPY ./docker/php85/php-fpm.d/www.conf /etc/php85/php-fpm.d/www.conf
COPY ./docker/php85/php.ini /etc/php85/php.ini
COPY ./docker/php85/healthcheck.sh /usr/local/bin/php-fpm-healthcheck
COPY ./docker/php85/entrypoint.sh /usr/local/bin/php-fpm-entrypoint

RUN addgroup -g "${APP_GID}" profundarium \
    && adduser -D -u "${APP_UID}" -G profundarium -h /srv/profundarium profundarium

WORKDIR /srv/profundarium

# Install dependencies using the same PHP runtime as the final image.
FROM runtime AS build
RUN apk add --no-cache curl
# Keep the version and SHA-256 from https://getcomposer.org/download/ in sync.
RUN curl -fsSLo /usr/local/bin/composer https://getcomposer.org/download/2.10.3/composer.phar \
    && echo '7a2d379d5b8ffdaa028580ef26494c36d2feef4b178d3dd1473a4dbc5e17c8d6  /usr/local/bin/composer' | sha256sum -c - \
    && chmod 755 /usr/local/bin/composer

COPY --chown=profundarium:profundarium . .
USER profundarium
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

FROM runtime
ENV LOG_CHANNEL=stderr \
    LARAVEL_STORAGE_PATH=/tmp/profundarium-storage
COPY --from=build --chown=profundarium:profundarium /srv/profundarium /srv/profundarium
# Docker initializes fresh named volumes with this mountpoint ownership.
# The non-root asset initializer can then populate /export-public without chown.
RUN mkdir -p /export-public storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && chown -R profundarium:profundarium /export-public storage bootstrap/cache \
    && chmod 755 /usr/local/bin/php-fpm-healthcheck

USER profundarium

EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD ["/usr/local/bin/php-fpm-healthcheck"]
ENTRYPOINT ["/bin/sh", "/usr/local/bin/php-fpm-entrypoint"]
CMD ["php-fpm85", "-F"]
