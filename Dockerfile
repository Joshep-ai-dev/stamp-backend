FROM php:8.5-cli-alpine

RUN apk add --no-cache icu-dev libpq-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install intl mbstring pcntl pdo_pgsql zip
COPY docker/php-upload.ini /usr/local/etc/php/conf.d/uploads.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader
COPY . .
RUN composer run-script post-autoload-dump && chown -R www-data:www-data storage bootstrap/cache public/images
USER www-data
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
