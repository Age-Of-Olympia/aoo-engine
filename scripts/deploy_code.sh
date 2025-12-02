echo "$(date)<br>"
cd ~/aoo-engine \
&& git pull \
&& git log --oneline -1
export HOME="/home/$(whoami)" \
&& cd ~/public_html/ \
&& echo -e "copie des fichiers composer :\n " \
&& cp -rav ~/aoo-engine/{composer.json,composer.lock} ~/public_html/ \
&& echo -e "composer install + generation autoload :\n " \
&& ~/bin/composer install \
&& ~/bin/composer dump-autoload -o \
&& echo -e "copie du reste des fichiers :\n " \
&& cp -ra ~/aoo-engine/{*.html,*.php,admin,api,config,Classes,css,js,scripts,src} ~/public_html/