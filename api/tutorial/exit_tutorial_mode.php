<?php
/**
 * Exit tutorial mode - switch back to real player
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once(__DIR__ . '/../../config/constants.php');
require_once(__DIR__ . '/../../config/db_constants.php');
require_once(__DIR__ . '/../../config/bootstrap.php');

use Doctrine\DBAL\DriverManager;

try {
    $playerId = $_SESSION['playerId'] ?? null;

    if (!$playerId) {
        echo json_encode([
            'success' => false,
            'error' => 'No player ID in session'
        ]);
        exit;
    }

    $conn = DriverManager::getConnection([
        'dbname' => DB_CONSTANTS['dbname'],
        'user' => DB_CONSTANTS['user'],
        'password' => DB_CONSTANTS['password'],
        'host' => str_replace(':3306', '', DB_CONSTANTS['host']),
        'driver' => 'pdo_mysql',
    ]);

    // Get the real player ID for this tutorial character
    $result = $conn->fetchAssociative(
        "SELECT real_player_id FROM tutorial_players WHERE player_id = ?",
        [$playerId]
    );

    if (!$result || !$result['real_player_id']) {
        echo json_encode([
            'success' => false,
            'error' => 'Not a tutorial character or real player not found'
        ]);
        exit;
    }

    $realPlayerId = $result['real_player_id'];

    // Switch session back to real player
    $_SESSION['playerId'] = $realPlayerId;

    // Clear tutorial session variables
    unset($_SESSION['tutorial_session_id']);
    unset($_SESSION['tutorial_player_id']);

    error_log("[exit_tutorial_mode] Switched from tutorial player $playerId to real player $realPlayerId");

    echo json_encode([
        'success' => true,
        'real_player_id' => $realPlayerId,
        'message' => 'Successfully returned to main character'
    ]);

} catch (Exception $e) {
    error_log("[exit_tutorial_mode] Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
