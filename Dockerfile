FROM php:8.2-apache

# Enable required Apache modules
RUN a2enmod rewrite headers

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        mysqli \
        mbstring \
        gd \
        zip \
        intl \
        exif \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Use custom Apache vhost
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# Set recommended PHP settings
RUN { \
        echo 'upload_max_filesize = 20M'; \
        echo 'post_max_size = 20M'; \
        echo 'memory_limit = 256M'; \
        echo 'max_execution_time = 60'; \
        echo 'display_errors = Off'; \
        echo 'log_errors = On'; \
    } > /usr/local/etc/php/conf.d/bcoem.ini

WORKDIR /var/www/html
