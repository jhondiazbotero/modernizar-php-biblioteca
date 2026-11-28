FROM php:7.4.33-cli

RUN docker-php-ext-install pdo_mysql

WORKDIR /app

CMD ["vendor/bin/phpunit", "-c", "tests/integration/phpunit.xml"]
