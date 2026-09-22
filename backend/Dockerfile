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

# Dev dependencies are included on purpose: the demo seeder uses factories
# (fakerphp/faker). The lock-hash stamp lets the entrypoint tell whether a
# (volume-persisted) vendor/ still matches composer.lock.
RUN composer install --no-interaction --prefer-dist --optimize-autoloader \
    && sha1sum composer.lock | cut -d' ' -f1 > vendor/.composer-lock-hash

# Outside the bind-mounted app directory, so it can't be shadowed. The sed
# strips CRLF in case the script was checked out on Windows without
# .gitattributes applied.
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint \
    && chmod +x /usr/local/bin/docker-entrypoint

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
