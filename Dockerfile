FROM php:8.3-apache


ARG UID=1000
ARG GID=1000

# Enable Apache modulesdedede
RUN a2enmod rewrite

# Zip Extension
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    vim \
    git \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip

# Install any extensions you need
RUN docker-php-ext-install mysqli pdo pdo_mysql zip

RUN pecl install xdebug-beta

RUN groupadd --gid ${GID} vscode
RUN adduser --home /home/vscode --gid ${GID} --uid ${UID} vscode

COPY --chown=${UID}:${GID} config/docker-php-ext-xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Set the working directory to /var/www/html
WORKDIR /var/www/html

COPY ../. .

USER ${UID}:${GID}

ENV HOME=/home/vscode
