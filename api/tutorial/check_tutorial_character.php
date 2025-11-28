<?php
/**
 * Check if current player is a tutorial character
 * Returns info to show emergency exit button
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once(__DIR__ . '/../../config/constants.php');
require_once(__DIR__ . '/../../config/db_constants.php');
require_once(__DIR__ . '/../../config/bootstrap.php');

use Doctrine\DBAL\DriverManager;
use App\Tutorial\TutorialHelper;

try {
    // IMPORTANT: Use getActivePlayerId() to get tutorial player when in tutorial mode
    $playerId = TutorialHelper::getActivePlayerId();

    error_log("[check_tutorial_character] Active player ID: " . ($playerId ?? 'NULL'));

    if (!$playerId) {
        echo json_encode(['is_tutorial_character' => false, 'debug' => 'No player ID in session']);
        exit;
    }

    $conn = DriverManager::getConnection([
        'dbname' => DB_CONSTANTS['dbname'],
        'user' => DB_CONSTANTS['user'],
        'password' => DB_CONSTANTS['password'],
        'host' => str_replace(':3306', '', DB_CONSTANTS['host']),
        'driver' => 'pdo_mysql',
    ]);

    // Check if this player_id is a tutorial character (active or not)
    // We check without is_active filter because users can get stuck even after tutorial ends
    $result = $conn->fetchAssociative(
        "SELECT tp.real_player_id, p.name as real_player_name
         FROM tutorial_players tp
         LEFT JOIN players p ON p.id = tp.real_player_id
         WHERE tp.player_id = ?",
        [$playerId]
    );

    error_log("[check_tutorial_character] Query result: " . json_encode($result));

    if ($result) {
        echo json_encode([
            'is_tutorial_character' => true,
            'real_player_id' => $result['real_player_id'],
            'real_player_name' => $result['real_player_name']
        ]);
    } else {
        echo json_encode([
            'is_tutorial_character' => false,
            'debug' => "No tutorial_players record found for player_id: $playerId"
        ]);
    }

} catch (Exception $e) {
    error_log("[check_tutorial_character] Error: " . $e->getMessage());
    echo json_encode([
        'is_tutorial_character' => false,
        'error' => $e->getMessage()
    ]);
}
