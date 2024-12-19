FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git unzip zip libpq-dev libonig-dev && \
    docker-php-ext-install pdo_pgsql

COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN php artisan route:cache

RUN chown -R www-data:www-data /app

EXPOSE 9000

CMD php artisan config:cache && php-fpm
