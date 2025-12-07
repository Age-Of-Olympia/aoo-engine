echo "\nsource /usr/share/bash-completion/completions/git" >> ~/.bashrc
./scripts/composer-setup.sh
mkdir -p /var/www/html/bin; mv composer.phar /var/www/html/bin/composer
composer install
composer dump-autoload