ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-fpm

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        default-mysql-client \
        procps \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        gd \
        intl \
        zip \
        exif \
        pcntl \
        opcache

RUN pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY php.ini /usr/local/etc/php/conf.d/zz-erp.ini
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
