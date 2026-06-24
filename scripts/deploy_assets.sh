#!/bin/bash
#
# Assets phase: copy game data (JSON + images) from the aoo-game-data checkout
# into this env's docroot. Invoked by admin/deploy.php with DOCROOT / SRC in the
# environment. No branch assertion here: aoo-game-data has its own branching.

source "$(dirname "$0")/deploy_lib.sh"
aoo_assert_env
[ -d "$SRC/aoo-game-data" ] || aoo_die "game-data checkout '$SRC/aoo-game-data' missing"

cd "$SRC/aoo-game-data" || exit 1
git pull || exit 1

cp -ra "$SRC/aoo-game-data/datas/public/actions"  "$DOCROOT/datas/public" || exit 1
cp -ra "$SRC/aoo-game-data/datas/public/dialogs"   "$DOCROOT/datas/public" || exit 1
cp -ra "$SRC/aoo-game-data/datas/public/factions"  "$DOCROOT/datas/public" || exit 1
cp -ra "$SRC/aoo-game-data/datas/public/items"     "$DOCROOT/datas/public" || exit 1
cp -ra "$SRC/aoo-game-data/datas/public/quests"    "$DOCROOT/datas/public" || exit 1
cp -ra "$SRC/aoo-game-data/datas/public/races"     "$DOCROOT/datas/public" || exit 1
cp -ra "$SRC/aoo-game-data/datas/public/crafts.json" "$DOCROOT/datas/public" || exit 1

cp -ra "$SRC/aoo-game-data/datas/private/dialogs"  "$DOCROOT/datas/private" || exit 1
cp -ra "$SRC/aoo-game-data/datas/private/maps"     "$DOCROOT/datas/private" || exit 1
cp -ra "$SRC/aoo-game-data/datas/private/plans"    "$DOCROOT/datas/private" || exit 1
cp -ra "$SRC/aoo-game-data/datas/private/races"    "$DOCROOT/datas/private" || exit 1

cp -ra "$SRC/aoo-game-data/img" "$DOCROOT/" || exit 1
