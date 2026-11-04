# Laravel runtime image (PHP-FPM). The code is NOT copied in: it is bind-mounted
# from ./app by compose, so edits show up instantly. What this image pins for
# everyone is the PHP version, the extensions and the Composer version.
FROM php:8.4-fpm

# Helper that installs PHP extensions with their system libraries
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

# grpc takes several minutes to compile - it sits in its own early layer so it is cached
RUN install-php-extensions grpc

RUN install-php-extensions pdo_mysql bcmath intl zip opcache pcntl protobuf

# Pinned Composer major version
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
