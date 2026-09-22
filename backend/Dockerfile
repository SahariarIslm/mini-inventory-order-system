FROM php:8.4-cli-alpine

RUN apk add --no-cache \
        bash \
        git \
        curl \
        libzip-dev \
        oniguruma-dev \
        mysql-client \
    && docker-php-ext-install pdo pdo_mysql mbstring zip bcmath pcntl

# Pull just the composer binary out of the official composer image —
# faster and cleaner than running its install script ourselves.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
