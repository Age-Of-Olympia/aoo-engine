#!/bin/bash
#
# SQL / migration phase of a code deploy. Runs BEFORE the code copy so the DB is
# already migrated when the new code lands (migrations must stay backward
# compatible — see the wiki). Invoked by admin/deploy.php with DOCROOT / SRC /
# EXPECT_BRANCH in the environment.

source "$(dirname "$0")/deploy_lib.sh"
aoo_assert_env

echo "$(date)<br>"

# 1. Bring the checkout to the right branch/code for THIS env, then verify it.
aoo_update_checkout
aoo_assert_branch

# 2. Dependencies in the checkout: provides vendor/bin/doctrine-migrations AND
#    the up-to-date migration classes the migrate step runs.
"$COMPOSER" install --no-dev --optimize-autoloader || exit 1

# 3. Point Doctrine at THIS env's database. db_constants.php is the unversioned
#    per-docroot source of truth; copy it one-way (docroot -> checkout) only.
[ -r "$DOCROOT/config/db_constants.php" ] || { echo "db_constants.php missing in $DOCROOT/config"; exit 1; }
cp "$DOCROOT/config/db_constants.php" "$SRC/aoo-engine/config/db_constants.php" || exit 1

# 4. Ship the legacy SQL updates and ensure the tracking dir exists
#    (DataBaseUpdateService writes there and dies if it is absent). mkdir -p
#    also creates $DOCROOT/db on a freshly provisioned docroot.
mkdir -p "$DOCROOT/db/updates_done"
cp -ra "$SRC/aoo-engine/db/updates" "$DOCROOT/db/" || exit 1

# 5. Prod only (BACKUP_DIR set): dump the database before migrating, the only
#    rollback when a migration has no trustworthy down(). Keeps the last 10.
if [ -n "${BACKUP_DIR:-}" ]; then
    mkdir -p "$BACKUP_DIR" || exit 1
    # host[:port], user, password, database from the env's db_constants.php.
    # A here-string, not < <(...): process substitution needs /dev/fd, absent
    # when PHP runs this script on the host.
    read -r DB_HOST DB_PORT DB_USER DB_NAME <<< "$(php -r '
        require "config/db_constants.php";
        [$h, $p] = array_pad(explode(":", DB_CONSTANTS["host"], 2), 2, "3306");
        echo "$h $p ", DB_CONSTANTS["user"], " ", DB_CONSTANTS["dbname"], "\n";')"
    [ -n "$DB_NAME" ] || aoo_die "cannot read the database settings"
    DUMP="$BACKUP_DIR/${DB_NAME}_$(date +%Y%m%d_%H%M%S)_$(git rev-parse --short HEAD).sql.gz"
    MYSQL_PWD="$(php -r 'require "config/db_constants.php"; echo DB_CONSTANTS["password"];')" \
        mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" --single-transaction --routines \
            --default-character-set=utf8mb4 "$DB_NAME" | gzip > "$DUMP.part"
    [ "${PIPESTATUS[0]}" -eq 0 ] || { rm -f "$DUMP.part"; aoo_die "database backup failed"; }
    mv "$DUMP.part" "$DUMP" || exit 1
    echo "backup: $DUMP<br>"
    ls -1t "$BACKUP_DIR"/*.sql.gz | tail -n +11 | xargs -r rm -f
fi

# 6. Run migrations from the checkout. --no-all-or-nothing is mandatory:
#    MariaDB auto-commits DDL, so the global transaction wrapper would fail.
./vendor/bin/doctrine-migrations migrate --no-interaction --no-all-or-nothing
