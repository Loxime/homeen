FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        bash \
        git \
        icu-dev \
        libxml2-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
    && docker-php-ext-install -j$(nproc) \
        dom \
        intl \
        mbstring \
        opcache \
        pdo_pgsql \
        xml \
        xmlwriter \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY docker/php/entrypoint.sh /usr/local/bin/homeen-entrypoint
COPY docker/php/scheduler.sh /usr/local/bin/homeen-scheduler
RUN chmod +x /usr/local/bin/homeen-entrypoint /usr/local/bin/homeen-scheduler

ENTRYPOINT ["homeen-entrypoint"]
CMD ["php-fpm"]
