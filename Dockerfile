FROM wordpress:6.7-php8.3-apache

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        libonig-dev \
        libwebp-dev \
        less \
        ghostscript \
    ; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" gd zip intl bcmath exif; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN a2enmod rewrite headers remoteip expires

COPY docker/apache-remoteip.conf /etc/apache2/conf-enabled/zz-remoteip.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-gfoss.ini
