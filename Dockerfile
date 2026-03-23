FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libonig-dev libsqlite3-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# App code is mounted; run composer install on host (path repo) or use published package
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
