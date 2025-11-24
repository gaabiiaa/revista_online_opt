# Imaginea de bază PHP (alegeți versiunea PHP pe care o folosiți)
FROM php:8.2-fpm-alpine

# Instalarea extensiilor necesare, inclusiv pdo_pgsql
# 'postgresql-dev' este necesar pentru a construi extensia
RUN apk update && \
    apk add --no-cache postgresql-dev && \
    docker-php-ext-install pdo pdo_pgsql && \
    rm -rf /var/cache/apk/*

# Copiați restul aplicației
WORKDIR /var/www/html
COPY . /var/www/html

# Schimbarea permisiunilor (dacă este necesar pentru sesiuni/cache)
RUN chown -R www-data:www-data /var/www/html

# Comanda de Start (Start Command)
CMD ["php-fpm"]
