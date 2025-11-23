FROM php:8.2-apache

# Install MySQL PDO and mysqli support
RUN apt-get update && \
    docker-php-ext-install pdo pdo_mysql mysqli && \
    docker-php-ext-enable pdo_mysql mysqli

WORKDIR /var/www/html
COPY . /var/www/html

EXPOSE 80
