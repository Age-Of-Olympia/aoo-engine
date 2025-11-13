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

// Define tutorial steps
$steps = [
    // Section 1: Welcome & World (Steps 0-4)
    [
        'step_number' => 0,
        'step_type' => 'dialog',
        'title' => 'Bienvenue!',
        'config' => [
            'dialog_id' => 'gaia_welcome',
            'text' => 'Parlez à Gaïa pour commencer votre apprentissage.',
            'requires_validation' => false
        ],
        'xp_reward' => 5
    ],
    [
        'step_number' => 1,
        'step_type' => 'welcome',
        'title' => 'Un jeu au tour par tour',
        'config' => [
            'text' => 'Age of Olympia est un <strong>jeu au tour par tour</strong>. Chaque tour dure 15 minutes. Vous devez planifier vos actions et les exécuter avant le prochain tour!',
            'target_selector' => null,
            'tooltip_position' => 'center',
            'requires_validation' => false
        ],
        'xp_reward' => 5
    ],
    [
        'step_number' => 2,
        'step_type' => 'info',
        'title' => 'Vous voici!',
        'config' => [
            'text' => 'Vous êtes le personnage au centre de la carte. Votre nom est <strong>{PLAYER_NAME}</strong>. Bienvenue dans le monde d\'Olympia!',
            'target_selector' => '#player-avatar',
            'tooltip_position' => 'bottom',
            'requires_validation' => false
        ],
        'xp_reward' => 5
    ],
    [
        'step_number' => 3,
        'step_type' => 'info',
        'title' => 'La carte en damier',
        'config' => [
            'text' => 'Le monde est composé de <strong>cases</strong>. Vous pouvez vous déplacer de case en case. Chaque case peut contenir des joueurs, des monstres, des objets...',
            'target_selector' => '#game-map',
            'tooltip_position' => 'top',
            'requires_validation' => false
        ],
        'xp_reward' => 5
    ],
    [
        'step_number' => 4,
        'step_type' => 'movement',
        'title' => 'Votre premier mouvement',
        'config' => [
            'text' => 'Cliquez sur une case adjacente pour vous déplacer. Essayez de bouger dans n\'importe quelle direction!',
            'target_selector' => '.tile.adjacent',
            'tooltip_position' => 'center',
            'requires_validation' => true,
            'validation_type' => 'any_movement',
            'validation_hint' => 'Cliquez sur une case adjacente pour vous déplacer',
            'context_changes' => [
                'unlimited_mvt' => true  // Unlimited movement for first move
            ]
        ],
        'xp_reward' => 10
    ],

    // Section 2: Movement Limits (Steps 5-6)
    [
        'step_number' => 5,
        'step_type' => 'movement_limit',
        'title' => 'Mouvements limités',
        'config' => [
            'text' => '<strong>ATTENTION!</strong> En jeu réel, vos mouvements sont <strong>limités</strong>. Regardez en haut : vous avez <span class="highlight">{MAX_MVT} Mouvements</span> par tour. Utilisez-les tous pour continuer!',
            'target_selector' => '#mvt-display',
            'tooltip_position' => 'bottom',
            'requires_validation' => true,
            'validation_type' => 'movements_depleted',
            'validation_hint' => 'Déplacez-vous {MAX_MVT} fois pour épuiser vos mouvements',
            'context_changes' => [
                'unlimited_mvt' => false,
                'set_mvt_limit' => 4
            ]
        ],
        'xp_reward' => 20
    ],
    [
        'step_number' => 6,
        'step_type' => 'info',
        'title' => 'Le système de tours',
        'config' => [
            'text' => 'Vos mouvements se <strong>régénèrent</strong> à chaque tour (toutes les 15 minutes). C\'est le moment de planifier votre prochaine action!',
            'target_selector' => '#next-turn-timer',
            'tooltip_position' => 'bottom',
            'requires_validation' => false
        ],
        'xp_reward' => 5
    ],

    // Section 3: Actions (Steps 7-9)
    [
        'step_number' => 7,
        'step_type' => 'action_intro',
        'title' => 'Points d\'Action',
        'config' => [
            'text' => 'En plus des mouvements, vous avez des <strong>Points d\'Action (A)</strong>. Ils permettent d\'effectuer des actions comme fouiller, attaquer, se reposer...',
            'target_selector' => '#action-display',
            'tooltip_position' => 'bottom',
            'requires_validation' => false,
            'context_changes' => [
                'set_mvt_limit' => 4,  // Restore movements
                'set_action_limit' => 2
            ]
        ],
        'xp_reward' => 5
    ],
    [
        'step_number' => 8,
        'step_type' => 'info',
        'title' => 'Actions disponibles',
        'config' => [
            'text' => 'Les actions disponibles dépendent de votre situation. Regardez le panneau d\'actions pour voir ce que vous pouvez faire.',
            'target_selector' => '#actions-panel',
            'tooltip_position' => 'left',
            'requires_validation' => false
        ],
        'xp_reward' => 5
    ],
    [
        'step_number' => 9,
        'step_type' => 'action',
        'title' => 'Pratique : Fouiller',
        'config' => [
            'text' => 'Essayez l\'action <strong>Fouiller</strong> pour chercher des objets ou de l\'or sur votre case!',
            'target_selector' => '.action[data-action="fouiller"]',
            'tooltip_position' => 'left',
            'requires_validation' => true,
            'validation_type' => 'action_used',
            'validation_params' => ['action' => 'fouiller'],
            'validation_hint' => 'Cliquez sur l\'action Fouiller'
        ],
        'xp_reward' => 15
    ],

    // Section 4: Combat (Steps 10-12)
    [
        'step_number' => 10,
        'step_type' => 'dialog',
        'title' => 'Apprendre le combat',
        'config' => [
            'dialog_id' => 'gaia_combat',
            'text' => 'Gaïa va vous apprendre à combattre.',
            'requires_validation' => false
        ],
        'xp_reward' => 5
    ],
    [
        'step_number' => 11,
        'step_type' => 'combat_intro',
        'title' => 'Le Combat',
        'config' => [
            'text' => 'Le combat utilise vos <strong>caractéristiques</strong> : <br>- <strong>CC</strong> (Capacité de Combat) : nombre de dés lancés<br>- <strong>F</strong> (Force) : dégâts infligés<br>- <strong>E</strong> (Endurance) : résistance aux dégâts',
            'target_selector' => '#characteristics-panel',
            'tooltip_position' => 'left',
            'requires_validation' => false
        ],
        'xp_reward' => 5
    ],
    [
        'step_number' => 12,
        'step_type' => 'combat',
        'title' => 'Attaquez!',
        'config' => [
            'text' => 'Attaquez l\'Âme d\'entraînement que Gaïa a créée pour vous! Cliquez dessus, puis sur l\'icône <span class="ra ra-crossed-swords"></span>.',
            'target_selector' => '.enemy.tutorial',
            'tooltip_position' => 'bottom',
            'requires_validation' => true,
            'validation_type' => 'combat_initiated',
            'validation_hint' => 'Attaquez l\'ennemi d\'entraînement'
        ],
        'xp_reward' => 25
    ],

    // Section 5: Completion (Step 13)
    [
        'step_number' => 13,
        'step_type' => 'dialog',
        'title' => 'Tutoriel terminé!',
        'config' => [
            'dialog_id' => 'gaia_completion',
            'text' => 'Félicitations! Vous avez terminé le tutoriel de base.',
            'requires_validation' => false
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
            (version, step_number, step_type, title, config, xp_reward, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 1)';

    try {
        $result = $db->exe($sql, [
            '1.0.0',
            $step['step_number'],
            $step['step_type'],
            $step['title'],
            $configJson,
            $step['xp_reward']
        ]);

        if ($result) {
            $successCount++;
            echo "✓ Step {$step['step_number']}: {$step['title']} ({$step['xp_reward']} XP)\n";
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
