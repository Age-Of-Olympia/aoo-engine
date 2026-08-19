#!/bin/bash
#
# Code phase of a deploy: copy the engine checkout into this env's docroot.
# Invoked by admin/deploy.php (after deploy_sql.sh) with DOCROOT / SRC /
# EXPECT_BRANCH in the environment.
#
# Only application code is copied. Runtime-writable state living inside the
# docroot is intentionally NOT touched and must never be added to the copy list:
#   - datas/public/classements/*.html   (rankings cache, refreshed by cron)
#   - datas/private/forum/*             (forum state)
#   - datas/private/players/*           (player/turn/tutorial state)
#   - datas/private/welcome.msg.html    (admin-edited)
#   - img/arene/*                       (generated screenshots)
#   - db/updates_done/                  (migration tracking)
# (datas/ and img/ are handled separately by deploy_assets.sh.)

source "$(dirname "$0")/deploy_lib.sh"
aoo_assert_env

echo "$(date)<br>"

# Checkout was already aligned + composer-installed by deploy_sql.sh (code
# deploys run SQL first); re-sync so a standalone run is correct, then verify.
aoo_update_checkout
aoo_assert_branch

# Dependencies first: copy the composer manifests and the vendor/ built in the
# checkout, then regenerate the optimized autoloader in the docroot.
# tools/: only the building composer is served (its gui enforces admin auth on
# public hosts); the rest of tools/ is local workshop material, never deployed.
echo -e "copie des dependances :\n " \
&& cp -a "$SRC/aoo-engine/composer.json" "$SRC/aoo-engine/composer.lock" "$DOCROOT/" \
&& cp -ra "$SRC/aoo-engine/vendor" "$DOCROOT/" \
&& echo -e "copie du code :\n " \
&& cp -ra "$SRC/aoo-engine"/{scripts,*.html,*.php,admin,api,config,Classes,css,js,src} "$DOCROOT/" \
&& mkdir -p "$DOCROOT/tools" \
&& cp -ra "$SRC/aoo-engine/tools/building-composer" "$DOCROOT/tools/" \
&& cd "$DOCROOT" \
&& "$COMPOSER" dump-autoload -o
