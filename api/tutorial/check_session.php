<?php
/**
 * Debug endpoint to check tutorial session state
 */

define('NO_LOGIN', true);
require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'in_tutorial' => $_SESSION['in_tutorial'] ?? null,
    'tutorial_session_id' => $_SESSION['tutorial_session_id'] ?? null,
    'tutorial_player_id' => $_SESSION['tutorial_player_id'] ?? null,
    'tutorial_consume_movements' => $_SESSION['tutorial_consume_movements'] ?? null,
    'playerId' => $_SESSION['playerId'] ?? null,
], JSON_PRETTY_PRINT);
