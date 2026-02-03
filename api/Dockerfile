FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git unzip libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && echo "display_errors=On" >> /usr/local/etc/php/conf.d/dev.ini \
    && echo "error_reporting=E_ALL" >> /usr/local/etc/php/conf.d/dev.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
