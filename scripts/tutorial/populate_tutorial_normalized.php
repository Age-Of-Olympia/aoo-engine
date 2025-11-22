<?php
/**
 * Populate tutorial tables with normalized schema
 *
 * Run with: php scripts/tutorial/populate_tutorial_normalized.php
 */

if (php_sapi_name() !== 'cli') {
    die('This script must be run from command line');
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/db_constants.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/bootstrap.php';

use Classes\Db;

$db = new Db();

echo "=== Populating Tutorial Steps (Normalized Schema) ===\n\n";

// Clear existing data
echo "Clearing existing tutorial step data...\n";
$db->exe('DELETE FROM tutorial_step_next_preparation');
$db->exe('DELETE FROM tutorial_step_context_changes');
$db->exe('DELETE FROM tutorial_step_highlights');
$db->exe('DELETE FROM tutorial_step_interactions');
$db->exe('DELETE FROM tutorial_step_features');
$db->exe('DELETE FROM tutorial_step_prerequisites');
$db->exe('DELETE FROM tutorial_step_validation');
$db->exe('DELETE FROM tutorial_step_ui');
$db->exe('DELETE FROM tutorial_steps');
$db->exe('DELETE FROM tutorial_dialogs');
echo "Done.\n\n";

$version = '1.0.0';

// Define all tutorial steps
$steps = [
    // ===== SECTION 1: INTRODUCTION =====
    [
        'step_id' => 'welcome',
        'step_number' => 1,
        'step_type' => 'info',
        'title' => 'Bienvenue !',
        'text' => 'Bienvenue dans Age of Olympia ! Ce tutoriel va vous apprendre les bases du jeu. Suivez les instructions pour découvrir comment explorer, récolter et combattre.',
        'next_step' => 'your_character',
        'xp_reward' => 5,
        'ui' => [
            'tooltip_position' => 'center',
            'interaction_mode' => 'blocking',
        ],
    ],
    [
        'step_id' => 'your_character',
        'step_number' => 2,
        'step_type' => 'info',
        'title' => 'Votre personnage',
        'text' => 'Voici <strong>votre personnage</strong> ! Il est représenté au centre du damier. C\'est vous dans le monde d\'Olympia.',
        'next_step' => 'meet_gaia',
        'xp_reward' => 5,
        'ui' => [
            'target_selector' => '.case[data-coords="0,0"]',
            'tooltip_position' => 'bottom',
            'interaction_mode' => 'blocking',
        ],
    ],
    [
        'step_id' => 'meet_gaia',
        'step_number' => 3,
        'step_type' => 'info',
        'title' => 'Gaïa, votre guide',
        'text' => 'Voici <strong>Gaïa</strong>, la déesse de la Terre. Elle sera votre guide tout au long de ce tutoriel. Cliquez sur elle pour voir sa fiche.',
        'next_step' => 'close_card',
        'xp_reward' => 5,
        'ui' => [
            'target_selector' => '.case[data-coords="1,0"]',
            'tooltip_position' => 'left',
            'interaction_mode' => 'semi-blocking',
        ],
        'validation' => [
            'requires_validation' => true,
            'validation_type' => 'ui_panel_opened',
            'validation_hint' => 'Cliquez sur Gaïa pour ouvrir sa fiche',
            'panel_id' => 'actions',
        ],
        'interactions' => [
            ['.case', 'Cases du damier'],
            ['image', 'Personnages'],
            ['.case-infos', 'Fiche personnage'],
        ],
    ],
    [
        'step_id' => 'close_card',
        'step_number' => 4,
        'step_type' => 'ui_interaction',
        'title' => 'Fermer la fiche',
        'text' => 'Vous pouvez <strong>fermer la fiche</strong> en cliquant sur le bouton X, sur une case vide, ou ailleurs sur le damier.',
        'next_step' => 'movement_intro',
        'xp_reward' => 5,
        'ui' => [
            'target_selector' => '#ui-card .close-card',
            'tooltip_position' => 'left',
            'interaction_mode' => 'semi-blocking',
            'show_delay' => 300,
        ],
        'validation' => [
            'requires_validation' => true,
            'validation_type' => 'ui_element_hidden',
            'validation_hint' => 'Fermez la fiche de personnage',
            'element_selector' => '#ui-card',
        ],
        'interactions' => [
            ['.case', 'Cases du damier'],
            ['.close-card', 'Bouton fermer'],
            ['#game-map', 'Zone de jeu'],
            ['svg', 'Fond du damier'],
        ],
    ],

    // ===== SECTION 2: MOVEMENT =====
    [
        'step_id' => 'movement_intro',
        'step_number' => 5,
        'step_type' => 'info',
        'title' => 'Se déplacer',
        'text' => 'Regardez les <strong>cases vertes</strong> autour de vous ! Ce sont les cases où vous pouvez vous déplacer. Cliquez sur l\'une d\'elles pour bouger.',
        'next_step' => 'first_move',
        'xp_reward' => 5,
        'ui' => [
            'target_selector' => '.case.go',
            'tooltip_position' => 'center',
            'interaction_mode' => 'blocking',
        ],
        'highlights' => [
            '.case.go',
        ],
    ],
    [
        'step_id' => 'first_move',
        'step_number' => 6,
        'step_type' => 'movement',
        'title' => 'Premier pas',
        'text' => 'Cliquez sur une <strong>case verte</strong> pour vous déplacer !',
        'next_step' => 'movement_limit_warning',
        'xp_reward' => 10,
        'ui' => [
            'target_selector' => '.case.go',
            'tooltip_position' => 'center',
            'interaction_mode' => 'semi-blocking',
        ],
        'validation' => [
            'requires_validation' => true,
            'validation_type' => 'any_movement',
            'validation_hint' => 'Déplacez-vous sur une case adjacente',
        ],
        'prerequisites' => [
            'mvt_required' => 1,
            'auto_restore' => true,
            'unlimited_mvt' => true,
        ],
        'interactions' => [
            ['.case', 'Cases du damier'],
            ['.case.go', 'Cases accessibles'],
            ['#go-rect', 'Bouton de déplacement (rectangle)'],
            ['#go-img', 'Bouton de déplacement (image)'],
        ],
    ],
    [
        'step_id' => 'movement_limit_warning',
        'step_number' => 7,
        'step_type' => 'info',
        'title' => 'Mouvements limités !',
        'text' => '<strong>Attention !</strong> En jeu réel, vos mouvements sont <strong>limités</strong>. Vous avez 4 mouvements par tour. Chaque déplacement en consomme 1.',
        'next_step' => 'show_characteristics',
        'xp_reward' => 5,
        'ui' => [
            'tooltip_position' => 'center',
            'interaction_mode' => 'blocking',
        ],
    ],
    [
        'step_id' => 'show_characteristics',
        'step_number' => 8,
        'step_type' => 'ui_interaction',
        'title' => 'Vos caractéristiques',
        'text' => 'Cliquez sur <strong>"Caractéristiques"</strong> pour voir vos stats, dont vos mouvements restants.',
        'next_step' => 'deplete_movements',
        'xp_reward' => 5,
        'ui' => [
            'target_selector' => '#show-caracs',
            'tooltip_position' => 'bottom',
            'interaction_mode' => 'semi-blocking',
        ],
        'validation' => [
            'requires_validation' => true,
            'validation_type' => 'ui_panel_opened',
            'validation_hint' => 'Ouvrez le panneau des caractéristiques',
            'panel_id' => 'characteristics',
        ],
        'interactions' => [
            ['#show-caracs', 'Bouton caractéristiques'],
        ],
    ],
    [
        'step_id' => 'deplete_movements',
        'step_number' => 9,
        'step_type' => 'movement',
        'title' => 'Épuisez vos mouvements',
        'text' => 'Maintenant, <strong>déplacez-vous jusqu\'à épuiser vos 4 mouvements</strong>. Regardez le compteur diminuer !',
        'next_step' => 'movements_depleted_info',
        'xp_reward' => 15,
        'ui' => [
            'target_selector' => '#mvt-counter',
            'tooltip_position' => 'right',
            'interaction_mode' => 'semi-blocking',
        ],
        'validation' => [
            'requires_validation' => true,
            'validation_type' => 'movements_depleted',
            'validation_hint' => 'Utilisez tous vos mouvements',
        ],
        'prerequisites' => [
            'mvt_required' => 4,
            'auto_restore' => true,
            'consume_movements' => true,
        ],
        'interactions' => [
            ['.case', 'Cases du damier'],
            ['.case.go', 'Cases accessibles'],
            ['#go-rect', 'Bouton de déplacement (rectangle)'],
            ['#go-img', 'Bouton de déplacement (image)'],
        ],
        'context_changes' => [
            ['consume_movements', 'true'],
        ],
    ],
    [
        'step_id' => 'movements_depleted_info',
        'step_number' => 10,
        'step_type' => 'info',
        'title' => 'Plus de mouvements !',
        'text' => 'Vous n\'avez plus de mouvements ! En jeu réel, ils se régénèrent à chaque tour (toutes les 18h). Pour le tutoriel, on vous les restaure.',
        'next_step' => 'actions_intro',
        'xp_reward' => 5,
        'ui' => [
            'tooltip_position' => 'center',
            'interaction_mode' => 'blocking',
        ],
        'next_preparation' => [
            ['restore_mvt', '4'],
        ],
    ],

    // ===== SECTION 3: ACTIONS =====
    [
        'step_id' => 'actions_intro',
        'step_number' => 11,
        'step_type' => 'info',
        'title' => 'Les Actions',
        'text' => 'En plus des mouvements, vous avez des <strong>Points d\'Action (PA)</strong>. Ils permettent de fouiller, attaquer, récolter...',
        'next_step' => 'click_yourself',
        'xp_reward' => 5,
        'ui' => [
            'tooltip_position' => 'center',
            'interaction_mode' => 'blocking',
        ],
        'prerequisites' => [
            'mvt_required' => 4,
            'pa_required' => 2,
            'auto_restore' => true,
        ],
    ],
    [
        'step_id' => 'click_yourself',
        'step_number' => 12,
        'step_type' => 'ui_interaction',
        'title' => 'Vos actions',
        'text' => '<strong>Cliquez sur votre personnage</strong> pour voir les actions disponibles.',
        'next_step' => 'actions_panel_info',
        'xp_reward' => 5,
        'ui' => [
            'target_selector' => '#current-player-avatar',
            'tooltip_position' => 'bottom',
            'interaction_mode' => 'semi-blocking',
        ],
        'validation' => [
            'requires_validation' => true,
            'validation_type' => 'ui_panel_opened',
            'validation_hint' => 'Cliquez sur votre personnage',
            'panel_id' => 'actions',
        ],
        'interactions' => [
            ['.case', 'Cases du damier'],
            ['image', 'Personnages'],
            ['#current-player-avatar', 'Avatar du joueur'],
        ],
    ],
    [
        'step_id' => 'actions_panel_info',
        'step_number' => 13,
        'step_type' => 'info',
        'title' => 'Panneau d\'actions',
        'text' => 'Voici vos <strong>actions disponibles</strong> ! Chaque action consomme des PA. Nous allons en tester une : la récolte de ressources.',
        'next_step' => 'close_card_for_tree',
        'xp_reward' => 5,
        'ui' => [
            'target_selector' => '#ui-card',
            'tooltip_position' => 'left',
            'interaction_mode' => 'blocking',
            'show_delay' => 300,
        ],
    ],

    // ===== SECTION 4: RESOURCE GATHERING =====
    [
        'step_id' => 'close_card_for_tree',
        'step_number' => 14,
        'step_type' => 'ui_interaction',
        'title' => 'Direction l\'arbre',
        'text' => 'Fermez cette fiche. Nous allons aller vers un <strong>arbre</strong> pour le récolter.',
        'next_step' => 'walk_to_tree',
        'xp_reward' => 5,
        'ui' => [
            'target_selector' => '#ui-card .close-card',
            'tooltip_position' => 'left',
            'interaction_mode' => 'semi-blocking',
            'auto_close_card' => true,
        ],
        'validation' => [
            'requires_validation' => true,
            'validation_type' => 'ui_element_hidden',
            'validation_hint' => 'Fermez la fiche',
            'element_selector' => '#ui-card',
        ],
        'interactions' => [
            ['.case', 'Cases du damier'],
            ['.close-card', 'Bouton fermer'],
        ],
    ],
    [
        'step_id' => 'walk_to_tree',
        'step_number' => 15,
        'step_type' => 'movement',
        'title' => 'Approchez de l\'arbre',
        'text' => 'Déplacez-vous vers l\'<strong>arbre</strong> marqué sur le damier. Vous devez être sur une case <strong>adjacente</strong> pour le récolter.',
        'next_step' => 'observe_tree',
        'xp_reward' => 10,
        'ui' => [
            'target_selector' => '.case[data-coords="0,1"]',
            'tooltip_position' => 'bottom',
            'interaction_mode' => 'semi-blocking',
        ],
        'validation' => [
            'requires_validation' => true,
            'validation_type' => 'adjacent_to_position',
            'validation_hint' => 'Approchez-vous de l\'arbre',
            'target_x' => 0,
            'target_y' => 1,
        ],
        'prerequisites' => [
            'mvt_required' => 4,
            'auto_restore' => true,
            'ensure_harvestable_tree_x' => 0,
            'ensure_harvestable_tree_y' => 1,
        ],
        'interactions' => [
            ['.case', 'Cases du damier'],
            ['.case.go', 'Cases accessibles'],
            ['#go-rect', 'Bouton de déplacement (rectangle)'],
            ['#go-img', 'Bouton de déplacement (image)'],
        ],
        'highlights' => [
            '.case[data-coords="0,1"]',
        ],
    ],
    [
        'step_id' => 'observe_tree',
        'step_number' => 16,
        'step_type' => 'ui_interaction',
        'title' => 'Observer l\'arbre',
        'text' => '<strong>Cliquez sur l\'arbre</strong> pour voir ses informations.',
        'next_step' => 'tree_info',
        'xp_reward' => 5,
        'ui' => [
            'target_selector' => '.case[data-coords="0,1"]',
            'tooltip_position' => 'bottom',
            'interaction_mode' => 'semi-blocking',
        ],
        'validation' => [
            'requires_validation' => true,
            'validation_type' => 'ui_panel_opened',
            'validation_hint' => 'Cliquez sur l\'arbre',
            'panel_id' => 'actions',
        ],
        'interactions' => [
            ['.case', 'Cases du damier'],
            ['.case[data-coords="0,1"]', 'L\'arbre'],
        ],
    ],
    [
        'step_id' => 'tree_info',
        'step_number' => 17,
        'step_type' => 'info',
        'title' => 'Ressource récoltable',
        'text' => 'Cet arbre est <strong>récoltable</strong> ! Vous voyez l\'indication "récoltable" dans sa fiche. L\'action <strong>Fouiller</strong> permet de récolter.',
        'next_step' => 'use_fouiller',
        'xp_reward' => 5,
        'ui' => [
            'target_selector' => '#ui-card',
            'tooltip_position' => 'left',
            'interaction_mode' => 'blocking',
            'show_delay' => 300,
        ],
    ],
    [
        'step_id' => 'use_fouiller',
        'step_number' => 18,
        'step_type' => 'action',
        'title' => 'Fouiller !',
        'text' => 'Cliquez sur <strong>Fouiller</strong> pour récolter du bois de l\'arbre.',
        'next_step' => 'action_consumed',
        'xp_reward' => 15,
        'ui' => [
            'target_selector' => '.action[data-action="fouiller"]',
            'tooltip_position' => 'left',
            'interaction_mode' => 'semi-blocking',
        ],
        'validation' => [
            'requires_validation' => true,
            'validation_type' => 'action_used',
            'validation_hint' => 'Utilisez l\'action Fouiller',
            'action_name' => 'fouiller',
        ],
        'prerequisites' => [
            'pa_required' => 1,
            'auto_restore' => true,
        ],
        'interactions' => [
            ['.action[data-action="fouiller"]', 'Bouton Fouiller'],
            ['.case-infos', 'Fiche'],
            ['button.action', 'Boutons d\'action'],
        ],
    ],
    [
        'step_id' => 'action_consumed',
        'step_number' => 19,
        'step_type' => 'info',
        'title' => 'Action consommée',
        'text' => 'Vous avez récolté du <strong>bois</strong> ! Remarquez que l\'action a consommé <strong>1 PA</strong>. Vos PA se régénèrent aussi à chaque tour.',
        'next_step' => 'open_inventory',
        'xp_reward' => 5,
        'ui' => [
            'tooltip_position' => 'center',
            'interaction_mode' => 'blocking',
        ],
    ],

    // ===== SECTION 5: INVENTORY =====
    [
        'step_id' => 'open_inventory',
        'step_number' => 20,
        'step_type' => 'ui_interaction',
        'title' => 'Votre inventaire',
        'text' => 'Ouvrez votre <strong>Inventaire</strong> pour voir le bois récolté.',
        'next_step' => 'inventory_wood',
        'xp_reward' => 5,
        'ui' => [
            'target_selector' => '#show-inventory',
            'tooltip_position' => 'bottom',
            'interaction_mode' => 'semi-blocking',
        ],
        'validation' => [
            'requires_validation' => true,
            'validation_type' => 'ui_interaction',
            'validation_hint' => 'Cliquez sur le bouton Inventaire',
            'element_clicked' => 'show-inventory',
        ],
        'interactions' => [
            ['#show-inventory', 'Bouton Inventaire'],
        ],
    ],
    [
        'step_id' => 'inventory_wood',
        'step_number' => 21,
        'step_type' => 'info',
        'title' => 'Du bois !',
        'text' => 'Voilà votre <strong>bois</strong> ! Les ressources récoltées vont dans votre inventaire. Vous pourrez les utiliser pour fabriquer des objets.',
        'next_step' => 'close_inventory',
        'xp_reward' => 5,
        'ui' => [
            'target_selector' => '.item-case[data-name="bois"]',
            'tooltip_position' => 'left',
            'interaction_mode' => 'blocking',
            'show_delay' => 300,
        ],
    ],
    [
        'step_id' => 'close_inventory',
        'step_number' => 22,
        'step_type' => 'ui_interaction',
        'title' => 'Retour au jeu',
        'text' => 'Fermez l\'inventaire pour revenir au jeu. Cliquez sur <strong>Damier</strong>.',
        'next_step' => 'combat_intro',
        'xp_reward' => 5,
        'ui' => [
            'target_selector' => '#show-damier',
            'tooltip_position' => 'bottom',
            'interaction_mode' => 'semi-blocking',
        ],
        'validation' => [
            'requires_validation' => true,
            'validation_type' => 'ui_panel_opened',
            'validation_hint' => 'Retournez au damier',
            'panel_id' => 'damier',
        ],
        'interactions' => [
            ['#show-damier', 'Bouton Damier'],
        ],
    ],

    // ===== SECTION 6: COMBAT =====
    [
        'step_id' => 'combat_intro',
        'step_number' => 23,
        'step_type' => 'info',
        'title' => 'Le Combat',
        'text' => 'Maintenant, passons au <strong>combat</strong> ! C\'est essentiel pour survivre dans Olympia. Un ennemi d\'entraînement va apparaître.',
        'next_step' => 'enemy_spawned',
        'xp_reward' => 5,
        'ui' => [
            'tooltip_position' => 'center',
            'interaction_mode' => 'blocking',
        ],
        'next_preparation' => [
            ['spawn_enemy', 'tutorial_dummy'],
        ],
    ],
    [
        'step_id' => 'enemy_spawned',
        'step_number' => 24,
        'step_type' => 'info',
        'title' => 'Votre adversaire',
        'text' => 'Voici une <strong>Âme d\'entraînement</strong> ! C\'est un ennemi inoffensif créé pour le tutoriel. Approchez-vous !',
        'next_step' => 'walk_to_enemy',
        'xp_reward' => 5,
        'ui' => [
            'target_selector' => '.tutorial-enemy',
            'tooltip_position' => 'bottom',
            'interaction_mode' => 'blocking',
            'show_delay' => 500,
        ],
        'prerequisites' => [
            'spawn_enemy' => 'tutorial_dummy',
        ],
    ],
    [
        'step_id' => 'walk_to_enemy',
        'step_number' => 25,
        'step_type' => 'movement',
        'title' => 'Approchez l\'ennemi',
        'text' => 'Déplacez-vous vers l\'<strong>Âme d\'entraînement</strong>. Vous devez être sur la <strong>même case</strong> ou adjacent pour attaquer.',
        'next_step' => 'click_enemy',
        'xp_reward' => 10,
        'ui' => [
            'target_selector' => '.tutorial-enemy',
            'tooltip_position' => 'bottom',
            'interaction_mode' => 'semi-blocking',
        ],
        'validation' => [
            'requires_validation' => true,
            'validation_type' => 'adjacent_to_position',
            'validation_hint' => 'Approchez-vous de l\'ennemi',
            'target_x' => 2,
            'target_y' => 1,
        ],
        'prerequisites' => [
            'mvt_required' => 4,
            'auto_restore' => true,
        ],
        'interactions' => [
            ['.case', 'Cases du damier'],
            ['.case.go', 'Cases accessibles'],
            ['#go-rect', 'Bouton de déplacement (rectangle)'],
            ['#go-img', 'Bouton de déplacement (image)'],
        ],
    ],
    [
        'step_id' => 'click_enemy',
        'step_number' => 26,
        'step_type' => 'ui_interaction',
        'title' => 'Cibler l\'ennemi',
        'text' => '<strong>Cliquez sur l\'Âme d\'entraînement</strong> pour voir ses informations et l\'option d\'attaque.',
        'next_step' => 'attack_enemy',
        'xp_reward' => 5,
        'ui' => [
            'target_selector' => '.tutorial-enemy',
            'tooltip_position' => 'bottom',
            'interaction_mode' => 'semi-blocking',
        ],
        'validation' => [
            'requires_validation' => true,
            'validation_type' => 'ui_panel_opened',
            'validation_hint' => 'Cliquez sur l\'ennemi',
            'panel_id' => 'actions',
        ],
        'interactions' => [
            ['.case', 'Cases du damier'],
            ['image', 'Personnages'],
            ['.tutorial-enemy', 'Ennemi du tutoriel'],
        ],
    ],
    [
        'step_id' => 'attack_enemy',
        'step_number' => 27,
        'step_type' => 'combat',
        'title' => 'Attaquez !',
        'text' => 'Cliquez sur <strong>Attaquer</strong> pour frapper l\'Âme d\'entraînement !',
        'next_step' => 'tutorial_complete',
        'xp_reward' => 20,
        'ui' => [
            'target_selector' => '.action[data-action="attaquer"]',
            'tooltip_position' => 'left',
            'interaction_mode' => 'semi-blocking',
        ],
        'validation' => [
            'requires_validation' => true,
            'validation_type' => 'action_used',
            'validation_hint' => 'Attaquez l\'ennemi',
            'action_name' => 'attaquer',
        ],
        'prerequisites' => [
            'pa_required' => 1,
            'auto_restore' => true,
        ],
        'interactions' => [
            ['.action[data-action="attaquer"]', 'Bouton Attaquer'],
            ['.case-infos', 'Fiche'],
            ['button.action', 'Boutons d\'action'],
        ],
    ],

    // ===== SECTION 7: COMPLETION =====
    [
        'step_id' => 'tutorial_complete',
        'step_number' => 28,
        'step_type' => 'completion',
        'title' => 'Tutoriel terminé !',
        'text' => '<strong>Félicitations !</strong> Vous avez terminé le tutoriel ! Vous savez maintenant vous déplacer, récolter des ressources et combattre. Bonne chance dans Olympia !',
        'next_step' => null,
        'xp_reward' => 50,
        'ui' => [
            'tooltip_position' => 'center',
            'interaction_mode' => 'blocking',
        ],
        'features' => [
            'celebration' => true,
            'show_rewards' => true,
            'redirect_delay' => 3000,
        ],
    ],
];

// Insert steps
$successCount = 0;
$errorCount = 0;

foreach ($steps as $step) {
    echo "Inserting step {$step['step_number']}: {$step['step_id']}... ";

    try {
        // 1. Insert main step
        $db->exe(
            'INSERT INTO tutorial_steps (version, step_id, next_step, step_number, step_type, title, text, xp_reward, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)',
            [
                $version,
                $step['step_id'],
                $step['next_step'],
                $step['step_number'],
                $step['step_type'],
                $step['title'],
                $step['text'],
                $step['xp_reward']
            ]
        );

        $stepDbId = $db->exe('SELECT LAST_INSERT_ID() as id')->fetch_assoc()['id'];

        // 2. Insert UI configuration
        $ui = $step['ui'] ?? [];
        $db->exe(
            'INSERT INTO tutorial_step_ui (step_id, target_selector, target_description, highlight_selector, tooltip_position, interaction_mode, blocked_click_message, show_delay, auto_advance_delay, allow_manual_advance, auto_close_card)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $stepDbId,
                $ui['target_selector'] ?? null,
                $ui['target_description'] ?? null,
                $ui['highlight_selector'] ?? null,
                $ui['tooltip_position'] ?? 'bottom',
                $ui['interaction_mode'] ?? 'blocking',
                $ui['blocked_click_message'] ?? null,
                $ui['show_delay'] ?? 0,
                $ui['auto_advance_delay'] ?? null,
                ($ui['allow_manual_advance'] ?? true) ? 1 : 0,
                ($ui['auto_close_card'] ?? false) ? 1 : 0,
            ]
        );

        // 3. Insert validation rules
        $val = $step['validation'] ?? [];
        $db->exe(
            'INSERT INTO tutorial_step_validation (step_id, requires_validation, validation_type, validation_hint, target_x, target_y, movement_count, action_name, action_charges_required, combat_required, panel_id, element_selector, element_clicked, dialog_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $stepDbId,
                ($val['requires_validation'] ?? false) ? 1 : 0,
                $val['validation_type'] ?? null,
                $val['validation_hint'] ?? null,
                $val['target_x'] ?? null,
                $val['target_y'] ?? null,
                $val['movement_count'] ?? null,
                $val['action_name'] ?? null,
                $val['action_charges_required'] ?? 1,
                ($val['combat_required'] ?? false) ? 1 : 0,
                $val['panel_id'] ?? null,
                $val['element_selector'] ?? null,
                $val['element_clicked'] ?? null,
                $val['dialog_id'] ?? null,
            ]
        );

        // 4. Insert prerequisites
        $prereq = $step['prerequisites'] ?? [];
        $db->exe(
            'INSERT INTO tutorial_step_prerequisites (step_id, mvt_required, pa_required, auto_restore, consume_movements, unlimited_mvt, unlimited_pa, spawn_enemy, ensure_harvestable_tree_x, ensure_harvestable_tree_y)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $stepDbId,
                $prereq['mvt_required'] ?? null,
                $prereq['pa_required'] ?? null,
                ($prereq['auto_restore'] ?? true) ? 1 : 0,
                ($prereq['consume_movements'] ?? false) ? 1 : 0,
                ($prereq['unlimited_mvt'] ?? false) ? 1 : 0,
                ($prereq['unlimited_pa'] ?? false) ? 1 : 0,
                $prereq['spawn_enemy'] ?? null,
                $prereq['ensure_harvestable_tree_x'] ?? null,
                $prereq['ensure_harvestable_tree_y'] ?? null,
            ]
        );

        // 5. Insert features
        $feat = $step['features'] ?? [];
        $db->exe(
            'INSERT INTO tutorial_step_features (step_id, celebration, show_rewards, redirect_delay)
             VALUES (?, ?, ?, ?)',
            [
                $stepDbId,
                ($feat['celebration'] ?? false) ? 1 : 0,
                ($feat['show_rewards'] ?? false) ? 1 : 0,
                $feat['redirect_delay'] ?? null,
            ]
        );

        // 6. Insert interactions (1:N)
        if (!empty($step['interactions'])) {
            foreach ($step['interactions'] as $interaction) {
                $db->exe(
                    'INSERT INTO tutorial_step_interactions (step_id, selector, description) VALUES (?, ?, ?)',
                    [$stepDbId, $interaction[0], $interaction[1] ?? null]
                );
            }
        }

        // 7. Insert highlights (1:N)
        if (!empty($step['highlights'])) {
            foreach ($step['highlights'] as $highlight) {
                $db->exe(
                    'INSERT INTO tutorial_step_highlights (step_id, selector) VALUES (?, ?)',
                    [$stepDbId, $highlight]
                );
            }
        }

        // 8. Insert context changes (1:N)
        if (!empty($step['context_changes'])) {
            foreach ($step['context_changes'] as $change) {
                $db->exe(
                    'INSERT INTO tutorial_step_context_changes (step_id, context_key, context_value) VALUES (?, ?, ?)',
                    [$stepDbId, $change[0], $change[1]]
                );
            }
        }

        // 9. Insert next step preparation (1:N)
        if (!empty($step['next_preparation'])) {
            foreach ($step['next_preparation'] as $prep) {
                $db->exe(
                    'INSERT INTO tutorial_step_next_preparation (step_id, preparation_key, preparation_value) VALUES (?, ?, ?)',
                    [$stepDbId, $prep[0], $prep[1]]
                );
            }
        }

        echo "OK\n";
        $successCount++;

    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $errorCount++;
    }
}

echo "\n=== Summary ===\n";
echo "Successfully inserted: $successCount steps\n";
echo "Errors: $errorCount\n";
echo "Total XP available: " . array_sum(array_column($steps, 'xp_reward')) . " XP\n";

// Verify
echo "\n=== Verification ===\n";
$result = $db->exe('SELECT COUNT(*) as total FROM tutorial_steps WHERE version = ?', [$version]);
$row = $result->fetch_assoc();
echo "Steps in tutorial_steps: {$row['total']}\n";

$result = $db->exe('SELECT COUNT(*) as total FROM tutorial_step_ui');
$row = $result->fetch_assoc();
echo "UI configs: {$row['total']}\n";

$result = $db->exe('SELECT COUNT(*) as total FROM tutorial_step_validation');
$row = $result->fetch_assoc();
echo "Validation configs: {$row['total']}\n";

echo "\n✓ Tutorial step population complete!\n";

// ===== TUTORIAL MAP SETUP =====
echo "\n=== Setting up Tutorial Map ===\n";

$plan = 'tutorial';

// Helper to get or create coords
function getOrCreateCoords($db, $x, $y, $plan) {
    $result = $db->exe("SELECT id FROM coords WHERE x = ? AND y = ? AND plan = ?", [$x, $y, $plan]);
    $row = $result->fetch_assoc();
    if ($row) return $row['id'];

    $db->exe("INSERT INTO coords (x, y, z, plan) VALUES (?, ?, 0, ?)", [$x, $y, $plan]);
    return $db->conn->insert_id;
}

// Add grass tiles for inner area (-3 to 3)
echo "Adding grass tiles (-3 to 3)...\n";
$tilesAdded = 0;
for ($x = -3; $x <= 3; $x++) {
    for ($y = -3; $y <= 3; $y++) {
        $coordsId = getOrCreateCoords($db, $x, $y, $plan);

        // Check if tile already exists
        $result = $db->exe("SELECT id FROM map_tiles WHERE coords_id = ?", [$coordsId]);
        if (!$result->fetch_assoc()) {
            $db->exe("INSERT INTO map_tiles (name, coords_id, foreground) VALUES ('havres', ?, 0)", [$coordsId]);
            $tilesAdded++;
        }
    }
}
echo "Added $tilesAdded new tiles.\n";

// Add stone walls around the border (at -4 and 4)
echo "Adding stone walls at border (-4/4)...\n";
$wallsAdded = 0;
for ($i = -4; $i <= 4; $i++) {
    // Top wall (y=4)
    $coordsId = getOrCreateCoords($db, $i, 4, $plan);
    $result = $db->exe("SELECT id FROM map_walls WHERE coords_id = ?", [$coordsId]);
    if (!$result->fetch_assoc()) {
        $db->exe("INSERT INTO map_walls (name, coords_id, damages) VALUES ('mur_pierre', ?, 0)", [$coordsId]);
        $wallsAdded++;
    }

    // Bottom wall (y=-4)
    $coordsId = getOrCreateCoords($db, $i, -4, $plan);
    $result = $db->exe("SELECT id FROM map_walls WHERE coords_id = ?", [$coordsId]);
    if (!$result->fetch_assoc()) {
        $db->exe("INSERT INTO map_walls (name, coords_id, damages) VALUES ('mur_pierre', ?, 0)", [$coordsId]);
        $wallsAdded++;
    }

    // Left wall (x=-4)
    $coordsId = getOrCreateCoords($db, -4, $i, $plan);
    $result = $db->exe("SELECT id FROM map_walls WHERE coords_id = ?", [$coordsId]);
    if (!$result->fetch_assoc()) {
        $db->exe("INSERT INTO map_walls (name, coords_id, damages) VALUES ('mur_pierre', ?, 0)", [$coordsId]);
        $wallsAdded++;
    }

    // Right wall (x=4)
    $coordsId = getOrCreateCoords($db, 4, $i, $plan);
    $result = $db->exe("SELECT id FROM map_walls WHERE coords_id = ?", [$coordsId]);
    if (!$result->fetch_assoc()) {
        $db->exe("INSERT INTO map_walls (name, coords_id, damages) VALUES ('mur_pierre', ?, 0)", [$coordsId]);
        $wallsAdded++;
    }
}
echo "Added $wallsAdded new walls.\n";

// Verify map setup
$result = $db->exe("SELECT COUNT(*) as cnt FROM map_tiles mt JOIN coords c ON mt.coords_id = c.id WHERE c.plan = 'tutorial'");
$row = $result->fetch_assoc();
echo "Total tiles on tutorial map: {$row['cnt']}\n";

$result = $db->exe("SELECT COUNT(*) as cnt FROM map_walls mw JOIN coords c ON mw.coords_id = c.id WHERE c.plan = 'tutorial'");
$row = $result->fetch_assoc();
echo "Total walls on tutorial map: {$row['cnt']}\n";

echo "\n✓ Tutorial map setup complete!\n";
echo "\n✓ All tutorial population complete!\n";
