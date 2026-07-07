FROM php:8.4.1-fpm-alpine

RUN apk add --no-cache \
    bash \
    git \
    curl \
    unzip \
    libpq-dev \
    oniguruma-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    mbstring \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html