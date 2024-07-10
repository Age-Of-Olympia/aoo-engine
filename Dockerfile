FROM php:8.0.28-apache

# Enable Apache modulesdedede
RUN a2enmod rewrite

# Zip Extension
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip

# Install any extensions you need
RUN docker-php-ext-install mysqli pdo pdo_mysql zip

# Set the working directory to /var/www/html
WORKDIR /var/www/html

COPY ../. .
