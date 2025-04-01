echo "$(date)<br>"
cd ~/aoo-engine
git pull \
&& git log --oneline -1 \
&& cp -ra ~/aoo-engine/db/updates ~/public_html/db/
echo \n
cd ~/public_html
./vendor/bin/doctrine-migrations migrate --no-interaction