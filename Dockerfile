FROM php:8.5.5-fpm-alpine

RUN php -m | grep -Eq '^curl$' \
    && php -m | grep -Eq '^mbstring$' \
    && php -m | grep -Eq '^pdo_sqlite$'

WORKDIR /var/www/html
