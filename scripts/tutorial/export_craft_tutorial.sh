#!/bin/bash

# Export 2.0.0-craft tutorial data to SQL file
# Usage: ./export_craft_tutorial.sh > craft_tutorial_data.sql

DB_HOST="mariadb-aoo4"
DB_USER="root"
DB_PASS="passwordRoot"
DB_NAME="aoo4_test"

echo "-- 2.0.0-craft Tutorial - Complete Configuration Export"
echo "-- Generated: $(date)"
echo ""
echo "-- ===== Tutorial Step UI Configurations ====="
mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME --default-character-set=utf8mb4 -e "
SELECT CONCAT(
  'INSERT INTO tutorial_step_ui (step_id, target_selector, tooltip_position, interaction_mode, show_delay, auto_advance_delay) VALUES (',
  ts.id, ', ',
  IF(tsu.target_selector IS NULL, 'NULL', CONCAT('\"', REPLACE(tsu.target_selector, '\"', '\\\\\"'), '\"')), ', ',
  IF(tsu.tooltip_position IS NULL, 'NULL', CONCAT('\"', tsu.tooltip_position, '\"')), ', ',
  IF(tsu.interaction_mode IS NULL, 'NULL', CONCAT('\"', tsu.interaction_mode, '\"')), ', ',
  COALESCE(tsu.show_delay, 0), ', ',
  COALESCE(tsu.auto_advance_delay, 0),
  ') ON DUPLICATE KEY UPDATE target_selector = VALUES(target_selector), tooltip_position = VALUES(tooltip_position);'
) FROM tutorial_steps ts
LEFT JOIN tutorial_step_ui tsu ON ts.id = tsu.step_id
WHERE ts.version = '2.0.0-craft' AND tsu.step_id IS NOT NULL;"

echo ""
echo "-- ===== Tutorial Step Validation Configurations ====="
mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME --default-character-set=utf8mb4 -e "
SELECT CONCAT(
  'INSERT INTO tutorial_step_validation (step_id, requires_validation, validation_type, validation_hint, target_x, target_y, panel_id) VALUES (',
  ts.id, ', ',
  COALESCE(tsv.requires_validation, 0), ', ',
  IF(tsv.validation_type IS NULL, 'NULL', CONCAT('\"', tsv.validation_type, '\"')), ', ',
  IF(tsv.validation_hint IS NULL, 'NULL', CONCAT('\"', REPLACE(tsv.validation_hint, '\"', '\\\\\"'), '\"')), ', ',
  IF(tsv.target_x IS NULL, 'NULL', tsv.target_x), ', ',
  IF(tsv.target_y IS NULL, 'NULL', tsv.target_y), ', ',
  IF(tsv.panel_id IS NULL, 'NULL', CONCAT('\"', tsv.panel_id, '\"')),
  ') ON DUPLICATE KEY UPDATE validation_type = VALUES(validation_type), validation_hint = VALUES(validation_hint);'
) FROM tutorial_steps ts
LEFT JOIN tutorial_step_validation tsv ON ts.id = tsv.step_id
WHERE ts.version = '2.0.0-craft';"

echo ""
echo "-- ===== Tutorial Step Prerequisites ====="
mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME --default-character-set=utf8mb4 -e "
SELECT CONCAT(
  'INSERT INTO tutorial_step_prerequisites (step_id, mvt_required, pa_required, auto_restore) VALUES (',
  ts.id, ', ',
  IF(tsp.mvt_required IS NULL, 'NULL', tsp.mvt_required), ', ',
  IF(tsp.pa_required IS NULL, 'NULL', tsp.pa_required), ', ',
  COALESCE(tsp.auto_restore, 0),
  ') ON DUPLICATE KEY UPDATE mvt_required = VALUES(mvt_required), pa_required = VALUES(pa_required);'
) FROM tutorial_steps ts
LEFT JOIN tutorial_step_prerequisites tsp ON ts.id = tsp.step_id
WHERE ts.version = '2.0.0-craft';"

echo ""
echo "-- ===== Tutorial Step Interactions ====="
mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME --default-character-set=utf8mb4 -e "
SELECT CONCAT(
  'INSERT INTO tutorial_step_interactions (step_id, selector) VALUES (',
  ts.id, ', \"',
  REPLACE(tsi.selector, '\"', '\\\\\"'),
  '\");'
) FROM tutorial_steps ts
INNER JOIN tutorial_step_interactions tsi ON ts.id = tsi.step_id
WHERE ts.version = '2.0.0-craft';"

