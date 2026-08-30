FROM php:8.4-apache


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

# Install Node.js 20.x
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs

# Utils
#
# `apt-get update` AVANT chaque installation, et non une seule fois en haut :
# sans lui, la couche dépend des listes laissées par une couche précédente.
# Selon l'état du cache, elle réussit, échoue, ou — pire — n'installe qu'une
# partie de ce qu'on lui demande. L'image de développement a tourné des mois
# sans `xvfb` ni client MariaDB alors qu'ils sont écrits ici, et personne ne
# pouvait le voir : Cypress et la réinitialisation de la base de test
# échouaient chacun dans leur coin, sans jamais désigner l'image.
RUN apt-get update && apt-get install -y \
    vim \
    git \
    rsync \
    make \
    python3 \
    default-mysql-client

# Install chrome and chromedriver
RUN apt-get update -qq -y && \
    apt-get install -y \
        libasound2 \
        libatk-bridge2.0-0 \
        libgtk-4-1 \
        libnss3 \
        xdg-utils \
        wget && \
    wget -q -O chrome-linux64.zip https://storage.googleapis.com/chrome-for-testing-public/137.0.7151.55/linux64/chrome-linux64.zip && \
    unzip chrome-linux64.zip && \
    rm chrome-linux64.zip && \
    mv chrome-linux64 /opt/chrome/ && \
    ln -s /opt/chrome/chrome /usr/local/bin/ && \
    wget -q -O chromedriver-linux64.zip https://storage.googleapis.com/chrome-for-testing-public/137.0.7151.55/linux64/chromedriver-linux64.zip && \
    unzip -j chromedriver-linux64.zip chromedriver-linux64/chromedriver && \
    rm chromedriver-linux64.zip && \
    mv chromedriver /usr/local/bin/

# Install Cypress dependencies for headless and GUI mode
RUN apt-get update && apt-get install -y \
    libgtk-3-0 \
    libgbm1 \
    libnotify4 \
    libxss1 \
    libxtst6 \
    xauth \
    xvfb \
    libx11-xcb1 \
    libxcb-dri3-0 \
    libxcomposite1 \
    libxcursor1 \
    libxdamage1 \
    libxi6 \
    libxrandr2 \
    libxrender1

# L'outillage promis est-il VRAIMENT là ?
#
# Une image de développement à laquelle il manque un outil ne se signale
# jamais : c'est l'outil qui échoue, plus tard, dans un contexte qui
# n'évoque pas l'image. On préfère un build qui casse tout de suite à un
# conteneur qui ment pendant six mois.
RUN set -eux; \
    for tool in xvfb-run Xvfb mysql mysqldump node npx; do \
        command -v "$tool" >/dev/null || { echo "ABSENT de l'image : $tool"; exit 1; }; \
    done

# Install any extensions you need
RUN docker-php-ext-configure gd --with-jpeg
RUN docker-php-ext-install mysqli pdo pdo_mysql zip gd

RUN pecl install xdebug-beta

RUN groupadd --gid ${GID} vscode
RUN adduser --home /home/vscode --gid ${GID} --uid ${UID} vscode

COPY --chown=${UID}:${GID} config/docker-php-ext-xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
COPY --chown=${UID}:${GID} config/docker-php-ext-gd.ini /usr/local/etc/php/conf.d/docker-php-ext-gd.ini
COPY --chown=${UID}:${GID} config/docker-php-memory.ini /usr/local/etc/php/conf.d/docker-php-memory.ini

# Set the working directory to /var/www/html
WORKDIR /var/www/html

#COPY ../. .

# Install Node.js dependencies and Cypress binary
RUN npx cypress install

USER ${UID}:${GID}

ENV HOME=/home/vscode

ENTRYPOINT ["./entrypoint.sh"]
