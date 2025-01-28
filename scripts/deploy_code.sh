echo "$(date)<br>"
cd ~/aoo-engine
git pull \
&& git log --oneline -1 \
&& cp -ra ~/aoo-engine/{*.php,admin,api,checks,config,classes,css,js,scripts,src} ~/public_html/