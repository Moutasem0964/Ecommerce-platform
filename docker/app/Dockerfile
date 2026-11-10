FROM php:8.4-fpm

# Helper that installs PHP extensions with their system libraries
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

# grpc takes several minutes to compile - it sits in its own early layer so it is cached
RUN install-php-extensions grpc

RUN install-php-extensions pdo_mysql bcmath intl zip opcache pcntl protobuf

RUN install-php-extensions redis

# Pinned Composer major version
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

# PHP / FPM settings and the startup script
COPY docker/app/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/app/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint && chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

# 1) Dependencies first:
COPY app/composer.json app/composer.lock ./
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install --no-scripts --no-autoloader --prefer-dist --no-interaction

# 2) The code, then the optimized class map
COPY app/ ./
RUN composer dump-autoload --optimize --no-interaction \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
                storage/logs storage/app/public storage/keys \
    && ln -sfn /var/www/html/storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
