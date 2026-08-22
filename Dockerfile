FROM php:8.3-fpm-bookworm

ARG APP_UID=1000
ARG APP_GID=1000

ENV COMPOSER_HOME=/tmp/composer \
    PATH="/var/www/html/vendor/bin:${PATH}"

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        default-mysql-client \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/local.ini /usr/local/etc/php/conf.d/99-local.ini
COPY docker/php/entrypoint.sh /usr/local/bin/laravel-entrypoint

# Match www-data to the Linux host user so bind-mounted files are never
# created as root.
RUN groupmod --gid "${APP_GID}" www-data \
    && usermod --uid "${APP_UID}" --gid "${APP_GID}" www-data \
    && mkdir -p /var/www/html /tmp/composer \
    && chmod +x /usr/local/bin/laravel-entrypoint \
    && chown -R www-data:www-data /var/www/html /tmp/composer

WORKDIR /var/www/html

USER www-data

EXPOSE 9000

ENTRYPOINT ["laravel-entrypoint"]

CMD ["php-fpm"]
