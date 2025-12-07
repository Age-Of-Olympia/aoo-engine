#!/bin/bash

# Create Test Database for Cypress Tests
# This script creates a clean aoo4_test database with pre-configured test characters

set -e

DB_HOST="mariadb-aoo4"
DB_USER="root"
DB_PASS="passwordRoot"
DB_NAME="aoo4_test"
SOURCE_DB="aoo4"

echo "========================================="
echo "Creating Test Database: $DB_NAME"
echo "========================================="

# Drop existing test database if it exists
echo "Dropping existing test database (if exists)..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "DROP DATABASE IF EXISTS $DB_NAME;"

# Create new test database
echo "Creating new test database..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Dump structure from source database
echo "Copying database structure from $SOURCE_DB..."
mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" \
  --no-data \
  --skip-triggers \
  --default-character-set=utf8mb4 \
  "$SOURCE_DB" | mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME"

echo "✅ Test database structure created"

# Copy essential data (keep database small)
echo "Copying essential configuration data..."

# Copy races
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" --default-character-set=utf8mb4 <<EOF
INSERT INTO races SELECT * FROM $SOURCE_DB.races;
EOF
echo "  ✅ Races copied"

# Copy items (needed for inventory/tutorial)
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" --default-character-set=utf8mb4 <<EOF
INSERT INTO items SELECT * FROM $SOURCE_DB.items;
EOF
echo "  ✅ Items copied"

# Copy tutorial steps
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" --default-character-set=utf8mb4 <<EOF
INSERT INTO tutorial_steps SELECT * FROM $SOURCE_DB.tutorial_steps;
INSERT INTO tutorial_step_ui SELECT * FROM $SOURCE_DB.tutorial_step_ui;
INSERT INTO tutorial_step_validation SELECT * FROM $SOURCE_DB.tutorial_step_validation;
INSERT INTO tutorial_step_prerequisites SELECT * FROM $SOURCE_DB.tutorial_step_prerequisites;
INSERT INTO tutorial_step_features SELECT * FROM $SOURCE_DB.tutorial_step_features;
INSERT INTO tutorial_step_highlights SELECT * FROM $SOURCE_DB.tutorial_step_highlights;
INSERT INTO tutorial_step_interactions SELECT * FROM $SOURCE_DB.tutorial_step_interactions;
INSERT INTO tutorial_step_context_changes SELECT * FROM $SOURCE_DB.tutorial_step_context_changes;
INSERT INTO tutorial_step_next_preparation SELECT * FROM $SOURCE_DB.tutorial_step_next_preparation;
EOF
echo "  ✅ Tutorial steps copied"

# Copy tutorial dialogs
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" --default-character-set=utf8mb4 <<EOF
INSERT INTO tutorial_dialogs SELECT * FROM $SOURCE_DB.tutorial_dialogs WHERE is_active = 1;
EOF
echo "  ✅ Tutorial dialogs copied"

# Copy coordinates (at least spawn points)
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" --default-character-set=utf8mb4 <<EOF
INSERT INTO coords SELECT * FROM $SOURCE_DB.coords WHERE plan IN ('gaia', 'tutorial', 'tertre_sauvage_s2', 'eryn_dolen_s2', 'faille_naine_s2');
EOF
echo "  ✅ Coordinates copied (spawn points + tutorial)"

# Copy map walls for tutorial plan
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" --default-character-set=utf8mb4 <<EOF
INSERT INTO map_walls
SELECT mw.* FROM $SOURCE_DB.map_walls mw
JOIN $SOURCE_DB.coords c ON mw.coords_id = c.id
WHERE c.plan = 'tutorial';
EOF
echo "  ✅ Tutorial map walls copied"

# Copy actions (needed for tutorial)
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" --default-character-set=utf8mb4 <<EOF
INSERT INTO actions SELECT * FROM $SOURCE_DB.actions;
EOF
echo "  ✅ Actions copied"

echo ""
echo "========================================="
echo "Creating Test Characters"
echo "========================================="

# Now run the SQL script to create test characters
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" --default-character-set=utf8mb4 < /var/www/html/scripts/testing/insert_test_characters.sql

echo ""
echo "========================================="
echo "✅ Test Database Setup Complete!"
echo "========================================="
echo ""
echo "Database: $DB_NAME"
echo "Test Characters Created:"
echo "  1. TestFreshPlayer (fresh, never logged in)"
echo "  2. TestTutorialStarted (started tutorial, mid-progress)"
echo "  3. TestTutorialCompleted (completed tutorial)"
echo "  4. TestTutorialSkipped (skipped tutorial)"
echo ""
echo "Password for all test accounts: 'testpass'"
echo ""
echo "To use this database in tests:"
echo "  Update config/db_constants.php to use 'aoo4_test'"
echo ""
