FROM php:8.4-fpm-alpine

# Build arguments for matching the host user (avoids permission issues on ./src)
ARG UID=1000
ARG GID=1000

# System packages needed to build common PHP extensions
RUN apk add --no-cache \
        git \
        curl \
        unzip \
        bash \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        $PHPIZE_DEPS

# PHP extensions commonly required by Laravel
RUN docker-php-ext-install \
        pdo_mysql \
        mysqli \
        bcmath \
        intl \
        zip \
        opcache

# Redis extension (via PECL)
RUN pecl install redis \
    && docker-php-ext-enable redis

# Composer (copied from the official image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create a non-root user that maps to the host user
RUN addgroup -g ${GID} appuser \
    && adduser -D -u ${UID} -G appuser appuser

WORKDIR /var/www/html

USER appuser

EXPOSE 9000
CMD ["php-fpm"]
