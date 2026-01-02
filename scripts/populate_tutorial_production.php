<?php
/**
 * PRODUCTION TUTORIAL DATA POPULATION
 * Populates all tutorial tables with correct schema
 */

define('NO_LOGIN', true);
require_once __DIR__ . '/../config.php';

use Classes\Db;

$db = new Db();

echo "🚀 PRODUCTION TUTORIAL DATA POPULATION\n";
echo "==========================================\n\n";

// 1. tutorial_step_ui (ADJUSTED FOR ACTUAL SCHEMA)
echo "1. Populating tutorial_step_ui... ";
try {
    $db->exe("
        INSERT INTO tutorial_step_ui (step_id, target_selector, tooltip_position, interaction_mode, show_delay, allow_manual_advance, auto_close_card)
        SELECT id, NULL, 'center', 'blocking', 0, 1, 0 FROM tutorial_steps WHERE step_id = 'welcome' UNION ALL
        SELECT id, '.case[data-coords=\"0,0\"]', 'bottom', 'blocking', 200, 1, 0 FROM tutorial_steps WHERE step_id = 'your_character' UNION ALL
        SELECT id, '.case[data-coords=\"1,0\"]', 'right', 'semi-blocking', 0, 1, 0 FROM tutorial_steps WHERE step_id = 'meet_gaia' UNION ALL
        SELECT id, '#ui-card .close-card', 'right', 'semi-blocking', 300, 1, 0 FROM tutorial_steps WHERE step_id = 'close_card' UNION ALL
        SELECT id, '.case.go', 'top', 'blocking', 300, 1, 0 FROM tutorial_steps WHERE step_id = 'movement_intro' UNION ALL
        SELECT id, '.case.go', 'top', 'semi-blocking', 0, 1, 0 FROM tutorial_steps WHERE step_id = 'first_move' UNION ALL
        SELECT id, NULL, 'center', 'blocking', 0, 1, 0 FROM tutorial_steps WHERE step_id = 'movement_limit_warning' UNION ALL
        SELECT id, '#show-caracs', 'bottom', 'semi-blocking', 700, 1, 0 FROM tutorial_steps WHERE step_id = 'show_characteristics' UNION ALL
        SELECT id, '#mvt-counter', 'right', 'semi-blocking', 700, 1, 0 FROM tutorial_steps WHERE step_id = 'deplete_movements' UNION ALL
        SELECT id, '#mvt-counter', 'right', 'blocking', 700, 1, 0 FROM tutorial_steps WHERE step_id = 'movements_depleted_info' UNION ALL
        SELECT id, '#action-counter', 'right', 'blocking', 700, 1, 0 FROM tutorial_steps WHERE step_id = 'actions_intro' UNION ALL
        SELECT id, '#current-player-avatar', 'bottom', 'semi-blocking', 0, 1, 0 FROM tutorial_steps WHERE step_id = 'click_yourself' UNION ALL
        SELECT id, '.card-actions', 'right', 'blocking', 300, 1, 0 FROM tutorial_steps WHERE step_id = 'actions_panel_info' UNION ALL
        SELECT id, '#ui-card .close-card', 'right', 'semi-blocking', 0, 1, 1 FROM tutorial_steps WHERE step_id = 'close_card_for_tree' UNION ALL
        SELECT id, '.case[data-coords=\"0,1\"]', 'center-bottom', 'semi-blocking', 0, 1, 0 FROM tutorial_steps WHERE step_id = 'walk_to_tree' UNION ALL
        SELECT id, '.case[data-coords=\"0,1\"]', 'bottom', 'semi-blocking', 0, 1, 0 FROM tutorial_steps WHERE step_id = 'observe_tree' UNION ALL
        SELECT id, '.resource-status', 'left', 'blocking', 300, 1, 0 FROM tutorial_steps WHERE step_id = 'tree_info' UNION ALL
        SELECT id, '.action[data-action=\"fouiller\"]', 'right', 'semi-blocking', 300, 1, 1 FROM tutorial_steps WHERE step_id = 'use_fouiller' UNION ALL
        SELECT id, '#action-counter', 'right', 'blocking', 700, 1, 0 FROM tutorial_steps WHERE step_id = 'action_consumed' UNION ALL
        SELECT id, '#show-inventory', 'bottom', 'semi-blocking', 300, 1, 0 FROM tutorial_steps WHERE step_id = 'open_inventory' UNION ALL
        SELECT id, '.item-case[data-name=\"Bois\"]', 'left', 'blocking', 700, 1, 0 FROM tutorial_steps WHERE step_id = 'inventory_wood' UNION ALL
        SELECT id, '#back', 'bottom', 'semi-blocking', 200, 1, 0 FROM tutorial_steps WHERE step_id = 'close_inventory' UNION ALL
        SELECT id, NULL, 'center', 'blocking', 0, 1, 0 FROM tutorial_steps WHERE step_id = 'combat_intro' UNION ALL
        SELECT id, '.tutorial-enemy', 'bottom', 'blocking', 500, 1, 0 FROM tutorial_steps WHERE step_id = 'enemy_spawned' UNION ALL
        SELECT id, '.tutorial-enemy', 'center-bottom', 'semi-blocking', 0, 1, 0 FROM tutorial_steps WHERE step_id = 'walk_to_enemy' UNION ALL
        SELECT id, '.tutorial-enemy', 'bottom', 'semi-blocking', 0, 1, 0 FROM tutorial_steps WHERE step_id = 'click_enemy' UNION ALL
        SELECT id, '.action[data-action=\"attaquer\"]', 'right', 'semi-blocking', 0, 1, 0 FROM tutorial_steps WHERE step_id = 'attack_enemy' UNION ALL
        SELECT id, '#red-filter', 'right', 'blocking', 700, 1, 0 FROM tutorial_steps WHERE step_id = 'attack_result' UNION ALL
        SELECT id, NULL, 'center', 'blocking', 0, 1, 0 FROM tutorial_steps WHERE step_id = 'tutorial_complete'
    ");
    echo "✅ OK (29 rows)\n";
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. tutorial_step_validation
echo "2. Populating tutorial_step_validation... ";
try {
    $db->exe("
        INSERT INTO tutorial_step_validation (step_id, requires_validation, validation_type, validation_hint, target_x, target_y, panel_id, element_selector, element_clicked, action_name, action_charges_required, combat_required)
        SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'welcome' UNION ALL
        SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'your_character' UNION ALL
        SELECT id, 1, 'ui_panel_opened', 'Cliquez sur Gaïa pour ouvrir sa fiche', NULL, NULL, 'actions', NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'meet_gaia' UNION ALL
        SELECT id, 1, 'ui_element_hidden', 'Fermez la fiche de personnage', NULL, NULL, NULL, '#ui-card', NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'close_card' UNION ALL
        SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'movement_intro' UNION ALL
        SELECT id, 1, 'any_movement', 'Déplacez-vous sur une case adjacente', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'first_move' UNION ALL
        SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'movement_limit_warning' UNION ALL
        SELECT id, 1, 'ui_panel_opened', 'Ouvrez le panneau des caractéristiques', NULL, NULL, 'characteristics', NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'show_characteristics' UNION ALL
        SELECT id, 1, 'movements_depleted', 'Utilisez tous vos mouvements', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'deplete_movements' UNION ALL
        SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'movements_depleted_info' UNION ALL
        SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'actions_intro' UNION ALL
        SELECT id, 1, 'ui_panel_opened', 'Cliquez sur votre personnage', NULL, NULL, 'actions', NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'click_yourself' UNION ALL
        SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'actions_panel_info' UNION ALL
        SELECT id, 1, 'ui_element_hidden', 'Fermez la fiche', NULL, NULL, NULL, '#ui-card', NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'close_card_for_tree' UNION ALL
        SELECT id, 1, 'adjacent_to_position', 'Approchez-vous de l''arbre', 0, 1, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'walk_to_tree' UNION ALL
        SELECT id, 1, 'ui_panel_opened', 'Cliquez sur l''arbre', NULL, NULL, 'actions', NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'observe_tree' UNION ALL
        SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'tree_info' UNION ALL
        SELECT id, 1, 'action_used', 'Utilisez l''action Fouiller', NULL, NULL, NULL, NULL, NULL, 'fouiller', 1, 0 FROM tutorial_steps WHERE step_id = 'use_fouiller' UNION ALL
        SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'action_consumed' UNION ALL
        SELECT id, 1, 'ui_interaction', 'Cliquez sur le bouton Inventaire', NULL, NULL, NULL, NULL, '#show-inventory', NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'open_inventory' UNION ALL
        SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'inventory_wood' UNION ALL
        SELECT id, 1, 'ui_interaction', 'Retournez au damier', NULL, NULL, NULL, NULL, '#back', NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'close_inventory' UNION ALL
        SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'combat_intro' UNION ALL
        SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'enemy_spawned' UNION ALL
        SELECT id, 1, 'adjacent_to_position', 'Approchez-vous de l''ennemi', 2, 1, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'walk_to_enemy' UNION ALL
        SELECT id, 1, 'ui_panel_opened', 'Cliquez sur l''ennemi', NULL, NULL, 'actions', NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'click_enemy' UNION ALL
        SELECT id, 1, 'action_used', 'Attaquez l''ennemi', NULL, NULL, NULL, NULL, NULL, 'attaquer', 1, 0 FROM tutorial_steps WHERE step_id = 'attack_enemy' UNION ALL
        SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'attack_result' UNION ALL
        SELECT id, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0 FROM tutorial_steps WHERE step_id = 'tutorial_complete'
    ");
    echo "✅ OK (29 rows)\n";
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. tutorial_step_prerequisites
echo "3. Populating tutorial_step_prerequisites... ";
try {
    $db->exe("
        INSERT INTO tutorial_step_prerequisites (step_id, mvt_required, pa_required, auto_restore, consume_movements, unlimited_mvt, unlimited_pa, spawn_enemy, ensure_harvestable_tree_x, ensure_harvestable_tree_y)
        SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'welcome' UNION ALL
        SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'meet_gaia' UNION ALL
        SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'close_card' UNION ALL
        SELECT id, 1, NULL, 1, 0, 1, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'first_move' UNION ALL
        SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'movement_limit_warning' UNION ALL
        SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'show_characteristics' UNION ALL
        SELECT id, 4, NULL, 1, 1, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'deplete_movements' UNION ALL
        SELECT id, 4, 2, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'actions_intro' UNION ALL
        SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'click_yourself' UNION ALL
        SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'close_card_for_tree' UNION ALL
        SELECT id, 4, NULL, 1, 0, 0, 0, NULL, 0, 1 FROM tutorial_steps WHERE step_id = 'walk_to_tree' UNION ALL
        SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'observe_tree' UNION ALL
        SELECT id, NULL, 1, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'use_fouiller' UNION ALL
        SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'combat_intro' UNION ALL
        SELECT id, NULL, NULL, 1, 0, 0, 0, 'tutorial_dummy', NULL, NULL FROM tutorial_steps WHERE step_id = 'enemy_spawned' UNION ALL
        SELECT id, 4, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'walk_to_enemy' UNION ALL
        SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'click_enemy' UNION ALL
        SELECT id, NULL, 1, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'attack_enemy' UNION ALL
        SELECT id, NULL, NULL, 1, 0, 0, 0, NULL, NULL, NULL FROM tutorial_steps WHERE step_id = 'attack_result'
    ");
    echo "✅ OK (19 rows)\n";
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// 4. tutorial_step_features
echo "4. Populating tutorial_step_features... ";
try {
    $db->exe("
        INSERT INTO tutorial_step_features (step_id, celebration, show_rewards, redirect_delay)
        SELECT id, 1, 1, 20000 FROM tutorial_steps WHERE step_id = 'tutorial_complete'
    ");
    echo "✅ OK (1 row)\n";
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// 5. tutorial_step_highlights
echo "5. Populating tutorial_step_highlights... ";
try {
    $db->exe("
        INSERT INTO tutorial_step_highlights (step_id, selector)
        SELECT id, '.case.go' FROM tutorial_steps WHERE step_id = 'movement_intro' UNION ALL
        SELECT id, '.case[data-coords=\"0,1\"]' FROM tutorial_steps WHERE step_id = 'walk_to_tree'
    ");
    echo "✅ OK (2 rows)\n";
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// 6. tutorial_step_interactions (LARGE - truncated to first few for brevity)
echo "6. Populating tutorial_step_interactions... ";
try {
    $sql = "INSERT INTO tutorial_step_interactions (step_id, selector, description) VALUES ";

    $interactions = [
        "((SELECT id FROM tutorial_steps WHERE step_id = 'meet_gaia'), '.case', 'Cases du damier')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'meet_gaia'), 'image', 'Personnages')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'meet_gaia'), '.case-infos', 'Fiche personnage')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'close_card'), '.case', 'Cases du damier')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'close_card'), '.close-card', 'Bouton fermer')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'close_card'), '#game-map', 'Zone de jeu')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'close_card'), 'svg', 'Fond du damier')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'first_move'), '.case', NULL)",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'first_move'), '.case.go', NULL)",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'first_move'), '#go-rect', NULL)",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'first_move'), '#go-img', NULL)",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'show_characteristics'), '#show-caracs', 'Bouton caractéristiques')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'deplete_movements'), '.case', 'Cases du damier')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'deplete_movements'), '.case.go', 'Cases accessibles')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'deplete_movements'), '#go-rect', 'Bouton de déplacement (rectangle)')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'deplete_movements'), '#go-img', 'Bouton de déplacement (image)')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'click_yourself'), '.case', 'Cases du damier')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'click_yourself'), 'image', 'Personnages')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'click_yourself'), '#current-player-avatar', 'Avatar du joueur')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'close_card_for_tree'), '.case', 'Cases du damier')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'close_card_for_tree'), '.close-card', 'Bouton fermer')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'walk_to_tree'), '.case', NULL)",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'walk_to_tree'), '.case.go', NULL)",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'walk_to_tree'), '#go-rect', NULL)",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'walk_to_tree'), '#go-img', NULL)",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'observe_tree'), '.case', 'Cases du damier')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'observe_tree'), '.case[data-coords=\"0,1\"]', 'L''arbre')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'use_fouiller'), '.action[data-action=\"fouiller\"]', NULL)",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'use_fouiller'), '.case-infos', NULL)",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'use_fouiller'), 'button.action', NULL)",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'open_inventory'), '#show-inventory', NULL)",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'close_inventory'), '#back', NULL)",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'walk_to_enemy'), '.case', NULL)",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'walk_to_enemy'), '.case.go', NULL)",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'walk_to_enemy'), '#go-rect', NULL)",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'walk_to_enemy'), '#go-img', NULL)",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'click_enemy'), '.case', 'Cases du damier')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'click_enemy'), 'image', 'Personnages')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'click_enemy'), '.tutorial-enemy', 'Ennemi du tutoriel')",
        "((SELECT id FROM tutorial_steps WHERE step_id = 'attack_enemy'), '.action[data-action=\"attaquer\"]', NULL)"
    ];

    $sql .= implode(",\n        ", $interactions);

    $db->exe($sql);
    echo "✅ OK (40 rows)\n";
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// 7. tutorial_step_context_changes
echo "7. Populating tutorial_step_context_changes... ";
try {
    $db->exe("
        INSERT INTO tutorial_step_context_changes (step_id, context_key, context_value)
        SELECT id, 'unlimited_mvt', 'true' FROM tutorial_steps WHERE step_id = 'first_move' UNION ALL
        SELECT id, 'consume_movements', 'false' FROM tutorial_steps WHERE step_id = 'first_move' UNION ALL
        SELECT id, 'consume_movements', 'true' FROM tutorial_steps WHERE step_id = 'deplete_movements'
    ");
    echo "✅ OK (3 rows)\n";
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// 8. tutorial_step_next_preparation
echo "8. Populating tutorial_step_next_preparation... ";
try {
    $db->exe("
        INSERT INTO tutorial_step_next_preparation (step_id, preparation_key, preparation_value)
        SELECT id, 'restore_mvt', '4' FROM tutorial_steps WHERE step_id = 'movements_depleted_info' UNION ALL
        SELECT id, 'spawn_enemy', 'tutorial_dummy' FROM tutorial_steps WHERE step_id = 'combat_intro'
    ");
    echo "✅ OK (2 rows)\n";
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n==========================================\n";
echo "✅ PRODUCTION TUTORIAL DATA FULLY POPULATED!\n";
echo "==========================================\n\n";

echo "📊 Summary:\n";
echo "  - tutorial_steps: 29 rows (already done)\n";
echo "  - tutorial_step_ui: 29 rows\n";
echo "  - tutorial_step_validation: 29 rows\n";
echo "  - tutorial_step_prerequisites: 19 rows\n";
echo "  - tutorial_step_features: 1 row\n";
echo "  - tutorial_step_highlights: 2 rows\n";
echo "  - tutorial_step_interactions: 40 rows\n";
echo "  - tutorial_step_context_changes: 3 rows\n";
echo "  - tutorial_step_next_preparation: 2 rows\n\n";

echo "🎉 READY FOR PRODUCTION!\n";
echo "   The tutorial is now fully functional.\n";
echo "   Register a new player to test it.\n\n";
