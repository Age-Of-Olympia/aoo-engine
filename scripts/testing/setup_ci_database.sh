#!/bin/bash
#
# CI-only: write the CI database config and seed the throwaway MariaDB service
# for the Cypress tutorial E2E job (cypress_tutorial_job in .gitlab-ci.yml).
#
# Expects DB_HOST / DB_USER / DB_PASS in the environment (set by the job).
# Not used outside CI — local/devcontainer seeding goes through
# scripts/testing/reset_test_database.sh.
set -e

# 1. CI database config, pointing at the job's database (DB_HOST:
#    127.0.0.1 for the in-container MariaDB, see start_ci_mariadb.sh).
cat > config/db_constants.php <<PHPEOF
<?php
define('DEV_MODE', true);
define('DB_CONSTANTS', array(
    'host'     => '${DB_HOST:-127.0.0.1}',
    'user'     => 'root',
    'psw'      => 'passwordRoot',
    'db'       => 'aoo4',
    'password' => 'passwordRoot',
    'dbname'   => 'aoo4',
    'driver'   => 'mysqli',
    'charset'  => 'utf8mb4',
));
PHPEOF

# 1b. Cypress only (ENABLE_TUTORIAL_V2=1) : force-enable the new tutorial
#     system — cypress-registered players get fresh auto-incremented IDs (not
#     the dev whitelist 1/2/3), so TutorialFeatureFlag must not fall back to
#     that whitelist or index.php logs "Tutorial enabled: NO". test_job must
#     NOT define it: TutorialFeatureFlagTest exercises the disabled paths.
if [ "${ENABLE_TUTORIAL_V2:-}" = "1" ]; then
cat >> config/db_constants.php <<'PHPEOF'
define('TUTORIAL_V2_ENABLED', true);
PHPEOF
fi

# 2. Wait for the mariadb service to accept connections (cold start).
until mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "SELECT 1" >/dev/null 2>&1; do
    echo "waiting for mariadb..."
    sleep 2
done

# 3. Base schema + reference data.
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" aoo4 < db/init_noupdates.sql

# 4. init_noupdates.sql already contains the pre-snapshot schema (race_actions,
#    etc.) but not the migration-tracking row for it; record it so migrate does
#    not try to recreate existing tables. Add one INSERT IGNORE per migration
#    whose effect is already baked into init_noupdates.sql.
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" aoo4 -e "
  INSERT IGNORE INTO doctrine_migration_versions (version, executed_at, execution_time)
  VALUES ('App\\\\Migrations\\\\Version20250427223731', NOW(), 0);
"

# 5. Apply the tutorial_* migrations the Cypress spec depends on.
#    --no-all-or-nothing is mandatory (MariaDB auto-commits DDL).
vendor/bin/doctrine-migrations migrate --no-interaction --allow-no-migration --no-all-or-nothing
