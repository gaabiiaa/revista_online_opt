# 1. Base Image: Use a specific, stable version of PHP with Apache
# You can change '8.2' to your required version (e.g., '8.3', '8.1')
FROM php:8.2-apache

# 2. Install Dependencies (uncomment and modify as needed)
# Example: Install required PHP extensions like MySQL support
# RUN apt-get update && \
#     apt-get install -y libpng-dev libjpeg-dev && \
#     rm -rf /var/lib/apt/lists/* && \
#     docker-php-ext-configure gd --with-jpeg && \
#     docker-php-ext-install gd pdo pdo_mysql

# Example: Enable the Apache Rewrite module for clean URLs
# RUN a2enmod rewrite

# 3. Set Web Root and Copy Application Code
# This sets the working directory inside the container
WORKDIR /var/www/html

# Copy all files from your current repository into the container's web root
COPY . /var/www/html

# 4. Optional: Install PHP Dependencies (if you use Composer)
# RUN if [ -f "composer.json" ]; then \
#       # Install Composer globally (optional step, often done in a separate build stage)
#       curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
#       # Run composer install
#       composer install --no-dev --optimize-autoloader; \
#     fi

# 5. Expose Port: Fly.io generally defaults to port 8080, but PHP/Apache defaults to 80
# The Fly.io proxy handles the mapping, but the internal port must be exposed.
EXPOSE 80
