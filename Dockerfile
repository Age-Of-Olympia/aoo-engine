FROM php:8.0.28-apache

# Enable Apache modulesdedede
RUN a2enmod rewrite
# Install any extensions you need
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Set the working directory to /var/www/html
WORKDIR /var/www/html

COPY ../. .
