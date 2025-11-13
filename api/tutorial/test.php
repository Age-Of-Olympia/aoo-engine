<?php
/**
 * Simple test endpoint to verify API is working
 */

define('NO_LOGIN', true);
require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json');

// Test session
$sessionInfo = [
    'has_session' => isset($_SESSION['playerId']),
    'player_id' => $_SESSION['playerId'] ?? null,
    'session_id' => session_id()
];

echo json_encode([
    'success' => true,
    'message' => 'API is working!',
    'session' => $sessionInfo,
    'timestamp' => date('Y-m-d H:i:s')
]);
