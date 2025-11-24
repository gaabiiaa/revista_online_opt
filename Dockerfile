# Imaginea de baza este php:8.2-fpm-alpine
FROM php:8.2-fpm-alpine

# Instalarea extensiilor necesare, inclusiv pdo_pgsql
RUN apk update && \
    apk add --no-cache postgresql-dev php82-session && \
    docker-php-ext-install pdo pdo_pgsql && \
    rm -rf /var/cache/apk/*

# Instaleaza Caddy (Serverul Web)
RUN apk add --no-cache caddy

# Copiați restul aplicației
WORKDIR /var/www/html
COPY . /var/www/html
COPY Caddyfile /etc/caddy/Caddyfile

# Schimba permisiunile
RUN chown -R www-data:www-data /var/www/html

# Comanda de Start: Ruleaza atat PHP-FPM cat si Caddy in foreground
CMD php-fpm -F & caddy run --config /etc/caddy/Caddyfile --adapter caddyfile
