#!/bin/bash
# Rebuild the database the PHPUnit suite is allowed to ruin.
#
# The legacy fixtures write real rows through the production paths, and their
# teardown is not exception-safe: the first foreign-key refusal abandons the
# rest. Pointed at the development world, an interrupted teardown leaves
# entities standing on tiles that later cases build on, and every poisoned run
# poisons the next. So the suite gets its own database, and this rebuilds it.
#
# Structure and reference catalogues are cloned from the live schema, so the
# suite always runs against the schema migrations actually produced — a
# hand-maintained dump would drift, which is exactly how aoo4_test ended up
# eighteen tables behind.
#
# Runs where a MariaDB client exists. The devcontainer image has none; the
# database container does, under its `mariadb-*` names.
#
#   DB_HOST/DB_USER/DB_PASS  — connection      (devcontainer defaults)
#   SOURCE_DB                — schema source   (default: aoo4)
#   TEST_DB                  — rebuilt         (default: aoo4_phpunit)

set -e

DB_HOST="${DB_HOST:-mariadb-aoo4}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-passwordRoot}"
SOURCE_DB="${SOURCE_DB:-aoo4}"
TEST_DB="${TEST_DB:-aoo4_phpunit}"

MYSQL="$(command -v mysql || command -v mariadb || true)"
DUMP="$(command -v mysqldump || command -v mariadb-dump || true)"

if [ -z "$MYSQL" ] || [ -z "$DUMP" ]; then
    echo "❌ Aucun client MariaDB ici."
    echo "   L'image du devcontainer n'en embarque pas. Depuis l'hôte :"
    echo "     docker exec -e DB_HOST=127.0.0.1 aoo-engine-mariadb-aoo4-1 \\"
    echo "       bash -s < scripts/testing/reset_phpunit_database.sh"
    exit 1
fi

echo "🔄 Reconstruction de $TEST_DB depuis $SOURCE_DB"

"$MYSQL" -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" \
    -e "DROP DATABASE IF EXISTS $TEST_DB;
        CREATE DATABASE $TEST_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

echo "📋 Structure…"
"$DUMP" -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" --no-data --skip-triggers "$SOURCE_DB" \
    | "$MYSQL" -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$TEST_DB"

# Catalogues only: what the game IS, never what has happened in it. A fixture
# needs its race and its item to exist; it must never meet another test's
# character, nor anybody's buildings.
echo "📦 Catalogues…"
for table in \
    races race_starter_actions race_spells race_harvest race_footprint \
    entity_type_footprints type_child_config \
    items recipes recipe_ingredients recipe_results \
    actions action_conditions action_outcomes outcome_instructions \
    action_type_logs action_type_xp action_type_instructions action_type_preconditions \
    action_condition_preconditions action_passives \
    effects effect_controls effect_corruption_materials effect_name_list \
    factions faction_roles dialogs dialog_lines skills passives \
    tile_colors tile_catalog migration_versions
do
    "$MYSQL" -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$TEST_DB" \
        -e "INSERT IGNORE INTO \`$table\` SELECT * FROM \`$SOURCE_DB\`.\`$table\`;" 2>/dev/null \
        && echo "  ✔ $table" || true
done

COUNT=$("$MYSQL" -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -N \
    -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$TEST_DB';")

echo ""
echo "✅ $TEST_DB prête — $COUNT tables, aucun personnage, aucun bâtiment."
echo "   La suite l'utilise d'office ; AOO_TEST_DB= (vide) la renvoie sur $SOURCE_DB."
