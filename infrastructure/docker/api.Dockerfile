FROM php:8.4-cli-alpine AS tooling
RUN apk add --no-cache libpq-dev oniguruma-dev libzip-dev curl-dev icu-dev sqlite-dev linux-headers git unzip \
 && docker-php-ext-install pdo_pgsql pdo_sqlite mbstring pcntl curl intl zip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /var/www/html

FROM tooling AS dependencies
COPY apps/api/composer.json apps/api/composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction --optimize-autoloader

FROM tooling AS runtime
RUN addgroup -g 1000 apiqendra && adduser -D -G apiqendra -u 1000 apiqendra
COPY apps/api/ ./
COPY --from=dependencies /var/www/html/vendor ./vendor
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private bootstrap/cache \
 && chown -R apiqendra:apiqendra storage bootstrap/cache
USER apiqendra
ENV PHP_CLI_SERVER_WORKERS=4
EXPOSE 8000
CMD ["php","artisan","serve","--host=0.0.0.0","--port=8000","--no-reload"]
