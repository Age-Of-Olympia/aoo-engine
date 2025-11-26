<?php
/**
 * Populate tutorial_configurations table with initial tutorial steps
 *
 * Run with: php scripts/tutorial/populate_tutorial_steps.php
 */

// CLI only
if (php_sapi_name() !== 'cli') {
    die('This script must be run from command line');
}

// Bootstrap
define('__ROOT__', dirname(dirname(__DIR__)));
require_once(__ROOT__ . '/config/constants.php');
require_once(__ROOT__ . '/config/db_constants.php');
require_once(__ROOT__ . '/config/bootstrap.php');
require_once(__ROOT__ . '/config/functions.php');

use Classes\Db;

$db = new Db();

echo "=== Populating Tutorial Steps ===\n\n";

// Clear existing steps for version 1.0.0
echo "Clearing existing steps for version 1.0.0...\n";
$db->exe('DELETE FROM tutorial_configurations WHERE version = ?', ['1.0.0']);

// Define tutorial steps with named IDs and next_step references
// This makes it easy to insert/reorder steps without renumbering everything
$steps = [
    // Section 1: Welcome & World
    [
        'step_id' => 'gaia_welcome',
        'step_number' => 0,
        'step_type' => 'ui_interaction',
        'title' => 'Bienvenue!',
        'next_step' => 'close_card',
        'config' => [
            'dialog_id' => 'gaia_welcome',
            'text' => '<strong>Cliquez sur Gaïa</strong> (le personnage à côté de vous) pour commencer votre apprentissage.',
            'target_selector' => null,
            'tooltip_position' => 'center',
            'requires_validation' => true,
            'validation_type' => 'ui_panel_opened',
            'validation_params' => ['panel' => 'actions'],
            'validation_hint' => 'Cliquez sur Gaïa pour ouvrir sa fiche',
            // Semi-blocking mode - allow clicking on nearby tiles and dialog
            'interaction_mode' => 'semi-blocking',
            'allowed_interactions' => [
                '.case',  // Allow clicking on any tile
                'image',  // Allow clicking on any character image
                '.case-infos',  // Allow interacting with the opened character card
                '.case-infos a',  // Allow clicking links
                '.case-infos button',  // Allow clicking buttons
                '.dialog-option'  // Allow clicking dialog choices
            ],
            'blocked_click_message' => 'Cliquez sur Gaïa (le personnage à côté de vous) pour lui parler.'
        ],
        'xp_reward' => 5
    ],
    [
        'step_id' => 'close_card',
        'step_number' => 0.5,
        'step_type' => 'ui_interaction',
        'title' => 'Fermer la fiche',
        'next_step' => 'turn_based_intro',
        'config' => [
            'text' => 'Vous pouvez <strong>fermer la fiche de personnage</strong> en cliquant sur le bouton de fermeture, sur une case vide, ou dans le vide du damier.',
            'target_selector' => '#ui-card',
            'tooltip_position' => 'left',
            'requires_validation' => true,
            'validation_type' => 'ui_element_hidden',
            'validation_params' => ['element' => '#ui-card'],
            // Semi-blocking mode - allow all ways to close
            'interaction_mode' => 'semi-blocking',
            'allowed_interactions' => [
                '.case',                        // Click on any tile
                'image',                        // Click on characters
                '#game-map',                    // Click on map container (void)
                'svg',                          // Click on SVG background (void)
                '.close-card',                  // Close button
                'button[data-action="close-card"]',  // Close button (explicit)
                '.action.close-card',           // Close button (full selector)
                '#ui-card .action',             // Any action button in card
                '#ui-card',                     // Card itself
                '.card-actions',                // Actions area in card
                '.card-actions button',         // Any button in actions area
            ],
            'blocked_click_message' => 'Cliquez sur le bouton de fermeture, une case, ou dans le vide pour fermer la fiche.'
        ],
        'xp_reward' => 0
    ],
    [
        'step_id' => 'turn_based_intro',
        'step_number' => 1,
        'step_type' => 'welcome',
        'title' => 'Un jeu au tour par tour',
        'next_step' => 'your_position',
        'config' => [
            'text' => 'Age of Olympia est un <strong>jeu au tour par tour</strong>. Chaque tour dure 18 heures. Vous devez planifier vos actions et les exécuter avant le prochain tour!',
            'target_selector' => null,
            'tooltip_position' => 'center',
            'requires_validation' => false,
            // Blocking mode (default for welcome)
            'blocked_click_message' => 'Lisez les instructions et cliquez sur "Suivant" pour continuer.'
        ],
        'xp_reward' => 5
    ],
    [
        'step_id' => 'your_position',
        'step_number' => 2,
        'step_type' => 'info',
        'title' => 'Vous voici!',
        'next_step' => 'map_grid',
        'config' => [
            'text' => 'Vous êtes le personnage au centre du damier. Votre nom est <strong>{PLAYER_NAME}</strong>. Bienvenue dans le monde d\'Olympia!',
            'target_selector' => '#current-player-avatar',
            'tooltip_position' => 'bottom',
            'requires_validation' => false,
            // Blocking mode (default for info)
            'blocked_click_message' => 'Observez votre personnage et cliquez sur "Suivant".'
        ],
        'xp_reward' => 5
    ],
    [
        'step_id' => 'map_grid',
        'step_number' => 3,
        'step_type' => 'info',
        'title' => 'La carte en damier',
        'next_step' => 'first_movement',
        'config' => [
            'text' => 'Le monde est composé de <strong>cases</strong>. Vous pouvez vous déplacer de case en case. Chaque case peut contenir des joueurs, des monstres, des objets...',
            'target_selector' => '#game-map',
            'tooltip_position' => 'top',
            'requires_validation' => false,
            // Blocking mode (default for info)
            'blocked_click_message' => 'Observez le damier et cliquez sur "Suivant" pour continuer.'
        ],
        'xp_reward' => 5
    ],
    [
        'step_id' => 'first_movement',
        'step_number' => 4,
        'step_type' => 'movement',
        'title' => 'Votre premier mouvement',
        'next_step' => 'show_characteristics',
        'config' => [
            'text' => 'Cliquez sur une <strong>case adjacente</strong> (en vert) pour vous déplacer. Essayez de bouger dans n\'importe quelle direction!',
            'target_selector' => '.case.go',
            'target_description' => 'cliquez sur une case adjacente (en vert)',
            'tooltip_position' => 'center',
            'requires_validation' => true,
            'validation_type' => 'any_movement',
            'validation_hint' => 'Cliquez sur une case adjacente pour vous déplacer',
            // Semi-blocking mode (default for movement)
            'allowed_interactions' => [
                '.case', '.case.go', '#go-rect', '#go-img',
                'image',  // Allow clicking on characters (doesn't interfere with movement)
                '.case-infos'  // Allow viewing character info if opened
            ],
            'blocked_click_message' => 'Pour vous déplacer, cliquez sur une case adjacente (en vert).',
            'context_changes' => [
                'unlimited_mvt' => true  // Unlimited movement for first move
            ],
            'prerequisites' => [
                'mvt' => 1,
                'auto_restore' => true
            ]
        ],
        'xp_reward' => 10
    ],

    // Section 2: Movement Limits & Characteristics
    [
        'step_id' => 'show_characteristics',
        'step_number' => 5,
        'step_type' => 'ui_interaction',
        'title' => 'Affichez vos caractéristiques',
        'next_step' => 'movement_limits_intro',
        'config' => [
            'text' => 'Vos caractéristiques (mouvements, actions, force...) sont cachées par défaut. Cliquez sur le bouton <strong>"Caractéristiques"</strong> dans le menu pour les afficher!',
            'target_selector' => '#show-caracs',
            'tooltip_position' => 'bottom',
            'requires_validation' => true,
            'validation_type' => 'ui_panel_opened',
            'validation_params' => ['panel' => 'characteristics'],
            'validation_hint' => 'Cliquez sur le bouton "Caractéristiques" pour afficher vos stats',
            // Semi-blocking mode
            'allowed_interactions' => [
                '#show-caracs',
                'image',  // Allow clicking on characters
                '.case-infos'  // Allow viewing character cards
            ],
            'blocked_click_message' => 'Cliquez sur le bouton "Caractéristiques" dans le menu pour continuer.'
        ],
        'xp_reward' => 5
    ],
    [
        'step_id' => 'movement_limits_intro',
        'step_number' => 6,
        'step_type' => 'info',
        'title' => 'Mouvements limités',
        'next_step' => 'deplete_movements',
        'config' => [
            'text' => '<strong>ATTENTION!</strong> En jeu réel, vos mouvements sont <strong>limités</strong>. Regardez le panneau : vous avez <span class="highlight">4 Mouvements (M)</span> par tour. Chaque déplacement consomme 1 mouvement.',
            'target_selector' => '#mvt-counter',
            'tooltip_position' => 'right',
            'requires_validation' => false,
            // Semi-blocking mode - allow viewing characters
            'allowed_interactions' => [
                'image',  // Allow clicking on characters
                '.case-infos'  // Allow viewing character cards
            ],
            'blocked_click_message' => 'Lisez l\'information sur les mouvements limités et cliquez sur "Suivant".',
            'context_changes' => [
                'unlimited_mvt' => false,
                'consume_movements' => true,  // Enable movement consumption from this step forward
                'set_mvt_limit' => 4
            ],
            'prerequisites' => [
                'mvt' => 4,
                'auto_restore' => true
            ]
        ],
        'xp_reward' => 5
    ],
    [
        'step_id' => 'deplete_movements',
        'step_number' => 7,
        'step_type' => 'movement_limit',
        'title' => 'Utilisez tous vos mouvements',
        'next_step' => 'turn_system',
        'config' => [
            'text' => 'Maintenant, <strong>déplacez-vous pour utiliser tous vos mouvements</strong>. Regardez le compteur diminuer à chaque déplacement! {MOVEMENT_HINT}',
            'target_selector' => '#mvt-counter',
            'tooltip_position' => 'right',
            'requires_validation' => true,
            'validation_type' => 'movements_depleted',
            'validation_hint' => 'Continuez à vous déplacer pour utiliser tous vos mouvements',
            // Semi-blocking mode
            'allowed_interactions' => [
                '.case', '.case.go', '#go-rect', '#go-img',
                'image',  // Allow clicking on characters
                '.case-infos'  // Allow viewing character cards
            ],
            'blocked_click_message' => 'Déplacez-vous sur les cases adjacentes pour utiliser vos mouvements.',
            // Enable movement consumption for this step
            'context_changes' => [
                'consume_movements' => true
            ],
            // Restore movements when advancing TO this step (from step 6)
            'prerequisites' => [
                'mvt' => 4,
                'auto_restore' => true
            ],
            'prepare_next_step' => [
                'restore_mvt' => 4,  // Restore for next step
                'restore_actions' => 2
            ]
        ],
        'xp_reward' => 15
    ],
    [
        'step_id' => 'turn_system',
        'step_number' => 8,
        'step_type' => 'info',
        'title' => 'Le système de tours',
        'next_step' => 'action_points_intro',
        'config' => [
            'text' => 'Vos mouvements se <strong>régénèrent</strong> à chaque tour (toutes les 18 heures). C\'est le moment de planifier votre prochaine action!',
            'target_selector' => '#next-turn-timer',
            'tooltip_position' => 'bottom',
            'requires_validation' => false,
            // Semi-blocking mode - allow viewing characters
            'allowed_interactions' => [
                'image',  // Allow clicking on characters
                '.case-infos'  // Allow viewing character cards
            ],
            'blocked_click_message' => 'Lisez l\'explication sur les tours et cliquez sur "Suivant".'
        ],
        'xp_reward' => 5
    ],

    // Section 3: Actions
    [
        'step_id' => 'action_points_intro',
        'step_number' => 9,
        'step_type' => 'action_intro',
        'title' => 'Points d\'Action',
        'next_step' => 'available_actions',
        'config' => [
            'text' => 'En plus des mouvements, vous avez des <strong>Points d\'Action (A)</strong>. Ils permettent d\'effectuer des actions comme fouiller, attaquer, se reposer...',
            'target_selector' => '#action-counter',
            'tooltip_position' => 'right',
            'requires_validation' => false,
            // Semi-blocking mode - allow viewing characters
            'allowed_interactions' => [
                'image',  // Allow clicking on characters
                '.case-infos'  // Allow viewing character cards
            ],
            'blocked_click_message' => 'Observez votre compteur de Points d\'Action et cliquez sur "Suivant".',
            'context_changes' => [
                'set_mvt_limit' => 4,  // Restore movements
                'set_action_limit' => 2
            ],
            'prerequisites' => [
                'mvt' => 4,
                'actions' => 2,
                'auto_restore' => true
            ]
        ],
        'xp_reward' => 5
    ],
    [
        'step_id' => 'available_actions',
        'step_number' => 10,
        'step_type' => 'ui_interaction',
        'title' => 'Actions disponibles',
        'next_step' => 'search_action',
        'config' => [
            'text' => 'Pour voir vos actions disponibles, <strong>cliquez sur votre personnage</strong> (au centre du damier). Cela ouvrira le panneau d\'actions.',
            'target_selector' => '#current-player-avatar',
            'tooltip_position' => 'left',
            'requires_validation' => true,
            'validation_type' => 'ui_panel_opened',
            'validation_params' => ['panel' => 'actions'],
            'validation_hint' => 'Cliquez sur votre personnage pour ouvrir le panneau d\'actions',
            // Semi-blocking mode - allow clicking on player's tile and avatar
            'interaction_mode' => 'semi-blocking',
            'allowed_interactions' => [
                '.case',                      // Allow clicking on any tile (to reach player)
                '.case[data-coords="0,0"]',   // Current player's tile (explicit)
                '#current-player-avatar',     // Current player's avatar image
                'image.current-player',       // Fallback selector
                'image',                      // Allow clicking on any character image
                '.case-infos'                 // Allow interacting with the opened panel
            ],
            'blocked_click_message' => 'Cliquez sur votre personnage au centre du damier pour voir vos actions disponibles.'
        ],
        'xp_reward' => 5
    ],
    [
        'step_id' => 'search_action',
        'step_number' => 11,
        'step_type' => 'action',
        'title' => 'Pratique : Fouiller',
        'next_step' => 'combat_dialog',
        'config' => [
            'text' => 'Essayez l\'action <strong>Fouiller</strong> pour chercher des objets ou de l\'or sur votre case!',
            'target_selector' => '.action[data-action="fouiller"]',
            'target_description' => 'cliquez sur l\'action Fouiller',
            'tooltip_position' => 'left',
            'requires_validation' => true,
            'validation_type' => 'action_used',
            'validation_params' => ['action' => 'fouiller'],
            'validation_hint' => 'Cliquez sur l\'action Fouiller',
            // Semi-blocking mode (default for action)
            'allowed_interactions' => [
                '.action[data-action="fouiller"]',
                'button.action',
                '.case-infos',  // Allow interacting with the panel
                '.case-infos a',  // Allow clicking links in the panel
                '.case-infos button'  // Allow clicking buttons in the panel
            ],
            'blocked_click_message' => 'Pour continuer, cliquez sur le bouton "Fouiller" dans le panneau d\'actions.',
            'prerequisites' => [
                'actions' => 1,
                'auto_restore' => true
            ],
            'prepare_next_step' => [
                'restore_actions' => 2
            ]
        ],
        'xp_reward' => 15
    ],

    // Section 4: Combat
    [
        'step_id' => 'combat_dialog',
        'step_number' => 12,
        'step_type' => 'dialog',
        'title' => 'Apprendre le combat',
        'next_step' => 'combat_intro',
        'config' => [
            'dialog_id' => 'gaia_combat',
            'text' => '<strong>Cliquez sur Gaïa</strong> pour apprendre le combat.',
            'target_selector' => null,  // Point to Gaia when we know her position
            'tooltip_position' => 'center',
            'requires_validation' => false,
            // Semi-blocking mode - allow clicking on NPCs and dialog
            'interaction_mode' => 'semi-blocking',
            'allowed_interactions' => [
                '.case',  // Allow clicking on any tile (to find NPCs)
                'image',  // Allow clicking on any character image
                '.case-infos',  // Allow interacting with the opened character card
                '.case-infos a',  // Allow clicking links
                '.case-infos button',  // Allow clicking buttons
                '.dialog-option'  // Allow clicking dialog choices
            ],
            'blocked_click_message' => 'Cliquez sur Gaïa pour lui parler et apprendre le combat.',
            'prepare_next_step' => [
                'spawn_enemy' => 'tutorial_dummy'  // Spawn enemy for combat
            ]
        ],
        'xp_reward' => 5
    ],
    [
        'step_id' => 'combat_intro',
        'step_number' => 13,
        'step_type' => 'combat_intro',
        'title' => 'Le Combat',
        'next_step' => 'combat_practice',
        'config' => [
            'text' => 'Le combat utilise vos <strong>caractéristiques</strong> : <br>- <strong>CC</strong> (Capacité de Combat) : nombre de dés lancés<br>- <strong>F</strong> (Force) : dégâts infligés<br>- <strong>E</strong> (Endurance) : résistance aux dégâts',
            'target_selector' => '#characteristics-panel',
            'tooltip_position' => 'left',
            'requires_validation' => false,
            // Semi-blocking mode - allow viewing characters
            'allowed_interactions' => [
                'image',  // Allow clicking on characters
                '.case-infos'  // Allow viewing character cards
            ],
            'blocked_click_message' => 'Lisez les explications sur le combat et cliquez sur "Suivant".'
        ],
        'xp_reward' => 5
    ],
    [
        'step_id' => 'combat_practice',
        'step_number' => 14,
        'step_type' => 'combat',
        'title' => 'Attaquez!',
        'next_step' => 'tutorial_complete',
        'config' => [
            'text' => 'Attaquez l\'<strong>Âme d\'entraînement</strong> que Gaïa a créée pour vous! Cliquez dessus, puis sur l\'icône <span class="ra ra-crossed-swords"></span>.',
            'target_selector' => '.enemy.tutorial',
            'target_description' => 'cliquez sur l\'ennemi d\'entraînement',
            'tooltip_position' => 'bottom',
            'requires_validation' => true,
            'validation_type' => 'combat_initiated',
            'validation_hint' => 'Attaquez l\'ennemi d\'entraînement',
            // Semi-blocking mode (default for combat)
            'allowed_interactions' => [
                '.enemy.tutorial', '.action[data-action="attack"]', '.ra-crossed-swords',
                'image',  // Allow clicking on characters
                '.case-infos'  // Allow viewing character cards
            ],
            'blocked_click_message' => 'Pour combattre, cliquez sur l\'Âme d\'entraînement puis sur l\'icône d\'attaque.',
            'prerequisites' => [
                'actions' => 1,
                'ensure_enemy' => 'tutorial_dummy',
                'auto_restore' => true
            ],
            'prepare_next_step' => [
                'restore_actions' => 2,
                'remove_enemy' => 'tutorial_dummy'  // Remove enemy after combat
            ]
        ],
        'xp_reward' => 25
    ],

    // Section 5: Completion
    [
        'step_id' => 'tutorial_complete',
        'step_number' => 15,
        'step_type' => 'dialog',
        'title' => 'Tutoriel terminé!',
        'next_step' => null,  // Final step
        'config' => [
            'dialog_id' => 'gaia_completion',
            'text' => 'Félicitations ! Vous avez terminé le tutoriel de base.',
            'requires_validation' => false,
            // Blocking mode (default for dialog)
            'blocked_click_message' => 'Parlez à Gaïa pour terminer le tutoriel.',
            'allowed_interactions' => ['.npc[data-npc="gaia"]', '.dialog-option']
        ],
        'xp_reward' => 50
    ],
];

// Insert steps
$successCount = 0;
$errorCount = 0;

foreach ($steps as $step) {
    $configJson = json_encode($step['config']);

    $sql = 'INSERT INTO tutorial_configurations
            (version, step_id, next_step, step_number, step_type, title, config, xp_reward, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)';

    try {
        $result = $db->exe($sql, [
            '1.0.0',
            $step['step_id'] ?? null,
            $step['next_step'] ?? null,
            $step['step_number'],
            $step['step_type'],
            $step['title'],
            $configJson,
            $step['xp_reward']
        ]);

        if ($result) {
            $successCount++;
            $stepLabel = $step['step_id'] ?? "#{$step['step_number']}";
            echo "✓ Step $stepLabel: {$step['title']} ({$step['xp_reward']} XP)\n";
        } else {
            $errorCount++;
            echo "✗ Failed to insert step {$step['step_number']}\n";
        }
    } catch (Exception $e) {
        $errorCount++;
        echo "✗ Error inserting step {$step['step_number']}: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Summary ===\n";
echo "Successfully inserted: $successCount steps\n";
echo "Errors: $errorCount\n";
echo "Total XP available: " . array_sum(array_column($steps, 'xp_reward')) . " XP\n";

// Verify insertion
echo "\n=== Verification ===\n";
$result = $db->exe('SELECT COUNT(*) as total FROM tutorial_configurations WHERE version = ?', ['1.0.0']);
if ($result) {
    $row = $result->fetch_assoc();
    echo "Steps in database: {$row['total']}\n";
}

echo "\n✓ Tutorial steps population complete!\n";
