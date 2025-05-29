FROM php:8.3-apache


ARG UID=1000
ARG GID=1000

# Enable Apache modulesdedede
RUN a2enmod rewrite

# Zip Extension
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    libpng-dev \
    libjpeg-dev \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip

# Utils
RUN apt-get install -y \
    vim \
    git \
    rsync \
    make \
    python3

# Install any extensions you need
RUN docker-php-ext-configure gd --with-jpeg
RUN docker-php-ext-install mysqli pdo pdo_mysql zip gd

RUN pecl install xdebug-beta

RUN groupadd --gid ${GID} vscode
RUN adduser --home /home/vscode --gid ${GID} --uid ${UID} vscode

COPY --chown=${UID}:${GID} config/docker-php-ext-xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
COPY --chown=${UID}:${GID} config/docker-php-ext-gd.ini /usr/local/etc/php/conf.d/docker-php-ext-gd.ini

# Set the working directory to /var/www/html
WORKDIR /var/www/html

COPY ../. .

USER ${UID}:${GID}

ENV HOME=/home/vscode

ENTRYPOINT ["./entrypoint.sh"]
