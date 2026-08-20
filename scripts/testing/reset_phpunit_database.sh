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

# Les DÉCLENCHEURS viennent avec la structure : certains portent une règle que
# le schéma seul ne dit pas — celui qui remplit `races.type_kind` pour un
# écrivain qui ignore la colonne, par exemple. Les omettre donnait une base qui
# ressemble à la vraie et ne se comporte pas comme elle.
echo "📋 Structure et déclencheurs…"
"$DUMP" -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" --no-data --triggers --routines "$SOURCE_DB" \
    | "$MYSQL" -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$TEST_DB"

# Catalogues only: what the game IS, never what has happened in it. A fixture
# needs its race and its item to exist; it must never meet another test's
# character, nor anybody's buildings.
echo "📦 Catalogues…"
MISSING=""
for table in \
    races race_starter_actions race_spells race_harvest race_recipes \
    entity_type_footprints \
    items craft_recipes craft_recipes_ingredients craft_recipes_results \
    actions action_conditions action_outcomes outcome_instructions \
    action_type_logs action_type_xp action_type_instructions action_type_preconditions \
    action_condition_preconditions action_passives \
    effects effect_controls effect_corruption_materials \
    factions faction_roles dialogs \
    tutorial_catalog tutorial_steps tutorial_step_ui tutorial_step_validation \
    tutorial_step_prerequisites tutorial_step_features tutorial_step_highlights \
    tutorial_step_interactions tutorial_step_context_changes \
    tutorial_step_next_preparation tutorial_npcs tutorial_dialogs tutorial_settings \
    tile_colors doctrine_migration_versions
do
    # Une table mal nommée se taisait : la copie échouait, `|| true` avalait
    # l'erreur, et la suite tournait sans recettes — d'où des cas qui se
    # sautaient ici et tournaient en intégration. Ce qui manque se dit.
    if "$MYSQL" -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$TEST_DB" \
        -e "INSERT IGNORE INTO \`$table\` SELECT * FROM \`$SOURCE_DB\`.\`$table\`;" 2>/dev/null
    then
        echo "  ✔ $table"
    else
        MISSING="$MISSING $table"
    fi
done

if [ -n "$MISSING" ]; then
    echo ""
    echo "⚠️  Catalogues non copiés (table absente ou renommée) :$MISSING"
    echo "   Corriger la liste — un catalogue manquant fait SAUTER des cas."
fi

COUNT=$("$MYSQL" -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -N \
    -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$TEST_DB';")

echo ""
echo "✅ $TEST_DB prête — $COUNT tables, aucun personnage, aucun bâtiment."
echo "   La suite l'utilise d'office ; AOO_TEST_DB= (vide) la renvoie sur $SOURCE_DB."
