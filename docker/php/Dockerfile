FROM php:8.5-fpm-alpine
RUN apk add --no-cache icu-dev libpq-dev oniguruma-dev libzip-dev sqlite-dev $PHPIZE_DEPS \
 && docker-php-ext-install pdo_pgsql pdo_sqlite mbstring intl zip pcntl \
 && pecl install redis && docker-php-ext-enable redis
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY docker/php/entrypoint.sh /usr/local/bin/rankviora-entrypoint
RUN chmod +x /usr/local/bin/rankviora-entrypoint
ENTRYPOINT ["rankviora-entrypoint"]
CMD ["php-fpm"]
