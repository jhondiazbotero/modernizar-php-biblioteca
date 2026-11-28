# Etapa 1: dependencias de Composer (solo producción)
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts \
    --ignore-platform-req=ext-pdo_mysql

# Etapa 2: la aplicación
FROM php:7.4.33-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf

COPY --from=vendor /app/vendor /var/www/app/vendor
COPY public /var/www/app/public
COPY legacy /var/www/app/legacy
COPY src /var/www/app/src
COPY templates /var/www/app/templates
