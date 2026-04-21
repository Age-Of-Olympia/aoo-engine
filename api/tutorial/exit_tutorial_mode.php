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
    $tutorialSessionId = $_SESSION['tutorial_session_id'] ?? null;
    $playerId = $_SESSION['playerId'] ?? null;

    if (!$tutorialSessionId && !$playerId) {
        echo json_encode([
            'success' => false,
            'error' => 'No tutorial session or player ID in session'
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

    // Get the real player ID using tutorial_session_id (preferred) or player_id (fallback).
    // Phase 4.5: link lives on the tutorial player's own row (players.real_player_id_ref);
    // tutorial_players keeps only session bookkeeping.
    if ($tutorialSessionId) {
        $result = $conn->fetchAssociative(
            "SELECT p.real_player_id_ref AS real_player_id
             FROM tutorial_players tp
             JOIN players p ON p.id = tp.player_id
             WHERE tp.tutorial_session_id = ? AND tp.is_active = 1",
            [$tutorialSessionId]
        );
    } else {
        // Fallback: try to find by player_id (in case session var is missing)
        $result = $conn->fetchAssociative(
            "SELECT p.real_player_id_ref AS real_player_id
             FROM tutorial_players tp
             JOIN players p ON p.id = tp.player_id
             WHERE tp.player_id = ? AND tp.is_active = 1",
            [$playerId]
        );
    }

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
