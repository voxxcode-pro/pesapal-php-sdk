FROM php:8.2-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    git unzip curl libcurl4-openssl-dev libzip-dev zip \
    && docker-php-ext-install curl zip

# Enable Apache Rewrite module
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy everything to Apache server root
COPY . /var/www/html

# Install Composer globally
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install PHP dependencies (if composer.json exists)
RUN if [ -f "composer.json" ]; then composer install; fi

# Set file permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
