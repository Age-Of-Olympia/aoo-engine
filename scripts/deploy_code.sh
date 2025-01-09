echo "===== Deploy ====="
echo $(date)
cd ~/aoo-engine
git pull \
&& git log --oneline -1 \
&& cp -ra ~/aoo-engine/{*.php,admin,api,config,classes,css,js,scripts,src} ~/public_html/