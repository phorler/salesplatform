FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        bash \
        git \
        unzip \
        oniguruma-dev \
        libzip-dev \
        icu-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        bcmath \
        intl \
        zip \
        gd \
        exif

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

EXPOSE 9000
CMD ["php-fpm"]
