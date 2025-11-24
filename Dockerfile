# Imaginea de baza PHP
FROM php:8.2-fpm-alpine

# Instaleaza extensiile PostgreSQL SI Caddy (serverul web)
RUN apk update && \
    apk add --no-cache postgresql-dev php8-session caddy && \
    docker-php-ext-install pdo pdo_pgsql && \
    rm -rf /var/cache/apk/*

# Copiaza fisierele aplicatiei
WORKDIR /var/www/html
COPY . /var/www/html

# Creeaza fisierul de configurare Caddy pentru a trimite traficul la PHP-FPM (Port 9000)
# Render va expune automat portul 10000
COPY Caddyfile /etc/caddy/Caddyfile

# Schimba permisiunile
RUN chown -R www-data:www-data /var/www/html

# Comanda de Start: Ruleaza atat PHP-FPM cat si Caddy in foreground
CMD php-fpm -F & caddy run --config /etc/caddy/Caddyfile --adapter caddyfile
