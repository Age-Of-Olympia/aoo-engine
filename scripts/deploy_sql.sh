#!/bin/bash
#
# SQL / migration phase of a code deploy. Runs BEFORE the code copy so the DB is
# already migrated when the new code lands (migrations must stay backward
# compatible — see the wiki). Invoked by admin/deploy.php with DOCROOT / SRC /
# EXPECT_BRANCH in the environment.

source "$(dirname "$0")/deploy_lib.sh"
aoo_assert_env
aoo_assert_branch

echo "$(date)<br>"

# 1. Refresh the source checkout for THIS env.
cd "$SRC/aoo-engine" || exit 1
git pull || exit 1
git log --oneline -1

# 2. Dependencies in the checkout: provides vendor/bin/doctrine-migrations AND
#    the up-to-date migration classes the migrate step runs.
~/bin/composer install --no-dev --optimize-autoloader || exit 1

# 3. Point Doctrine at THIS env's database. db_constants.php is the unversioned
#    per-docroot source of truth; copy it one-way (docroot -> checkout) only.
[ -r "$DOCROOT/config/db_constants.php" ] || { echo "db_constants.php missing in $DOCROOT/config"; exit 1; }
cp "$DOCROOT/config/db_constants.php" "$SRC/aoo-engine/config/db_constants.php" || exit 1

# 4. Ship the legacy SQL updates and ensure the tracking dir exists
#    (DataBaseUpdateService writes there and dies if it is absent). mkdir -p
#    also creates $DOCROOT/db on a freshly provisioned docroot.
mkdir -p "$DOCROOT/db/updates_done"
cp -ra "$SRC/aoo-engine/db/updates" "$DOCROOT/db/" || exit 1

# 5. Run migrations from the checkout. --no-all-or-nothing is mandatory:
#    MariaDB auto-commits DDL, so the global transaction wrapper would fail.
./vendor/bin/doctrine-migrations migrate --no-interaction --no-all-or-nothing
