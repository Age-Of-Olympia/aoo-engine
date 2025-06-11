echo "$(date)<br>"
cd ~/aoo-engine \
&& git pull \
&& git log --oneline -1
export HOME="/home/$(whoami)" \
&& cd ~/public_html/ \
&& cp -ra ~/aoo-engine/{composer.json,composer.lock} ~/public_html/
&& ~/bin/composer install \
&& ~/bin/composer dump-autoload -o \
&& cp -ra ~/aoo-engine/{*.html,*.php,admin,api,checks,config,Classes,css,js,scripts,src} ~/public_html/