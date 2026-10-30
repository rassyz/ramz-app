FROM php:8.4.27-fpm

# Environment variables
ENV PS1="\u@\h:\w\$ " \
    TZ="Asia/Jakarta" \
    COMPOSER_MEMORY_LIMIT=-1

# Install system dependencies
# NOTE: nodejs/npm are intentionally NOT installed from the distro repo here
# (Debian ships Node 20 + npm 9, too old for the current Laravel starter kit
# toolchain). Node.js 22 LTS is installed from NodeSource in the next step.
RUN apt-get update && apt-get install -y --no-install-recommends \
    $PHPIZE_DEPS \
    ca-certificates \
    curl \
    git \
    cron \
    nano \
    unzip \
    openssh-server \
    iputils-ping \
    telnet \
    netcat-openbsd \
    libmemcached-dev \
    libsasl2-dev \
    libmcrypt-dev \
    libreadline-dev \
    libgmp-dev \
    libzip-dev \
    libpq-dev \
    libjpeg-dev \
    libpng-dev \
    libfreetype6-dev \
    libwebp-dev \
    libicu-dev \
    libssl-dev \
    libmagickwand-dev \
    libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js 22 LTS + npm (NodeSource)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/* \
    && node --version && npm --version

# Configure and install PHP extensions
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        soap \
        exif \
        pcntl \
        intl \
        gmp \
        zip \
        pdo_mysql \
        pdo_pgsql \
        bcmath \
        gd

# Install PECL extensions
# Install separately to make build failures easier to identify
RUN pecl install redis \
    && docker-php-ext-enable redis

RUN pecl install mongodb \
    && docker-php-ext-enable mongodb

RUN pecl install imagick \
    && docker-php-ext-enable imagick

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

RUN pecl install memcached \
    && docker-php-ext-enable memcached

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Configure timezone
RUN ln -snf /usr/share/zoneinfo/Asia/Jakarta /etc/localtime \
    && echo "Asia/Jakarta" > /etc/timezone

# Laravel Scheduler
RUN echo "* * * * * root cd /var/www/html && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1" \
    > /etc/cron.d/laravel-scheduler \
    && chmod 0644 /etc/cron.d/laravel-scheduler

# Working directory
WORKDIR /var/www/html

# PHP configuration
COPY ./local.ini /usr/local/etc/php/conf.d/local.ini

# Artisan shortcuts
RUN printf '#!/bin/sh\nexec php /var/www/html/artisan "$@"\n' > /usr/local/bin/art \
    && printf '#!/bin/sh\nexec php /var/www/html/artisan migrate "$@"\n' > /usr/local/bin/migrate \
    && printf '#!/bin/sh\nexec php /var/www/html/artisan migrate:fresh --seed "$@"\n' > /usr/local/bin/fresh \
    && chmod +x /usr/local/bin/art /usr/local/bin/migrate /usr/local/bin/fresh

# Canonical frontend files (plain Vite package.json + vite.config.js).
# The starter kit ships Vite+ ("vp") files that cannot install/build in this
# container; the entrypoint copies these canonical files over the freshly
# created project on first run, so a clean `docker compose up -d --build`
# needs no manual step. They live outside the web root so nothing in the
# mounted project volume can overwrite them.
COPY ./frontend/ /usr/local/share/ramz-frontend/

# Custom entrypoint
COPY ./docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]

EXPOSE 9000

CMD ["php-fpm"]
