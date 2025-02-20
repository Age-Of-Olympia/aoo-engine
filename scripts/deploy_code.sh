echo "$(date)<br>"
cd ~/aoo-engine \
&& git pull \
&& git log --oneline -1
export HOME="/home/$(whoami)" \
&& cd ~/public_html/ \
&& ~/bin/composer install \
&& ~/bin/composer dump-autoload \
&& cp -ra ~/aoo-engine/{*.html,*.php,admin,api,checks,config,classes,css,js,scripts,src} ~/public_html/