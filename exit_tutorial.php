<?php
session_start();

echo "Current session:\n";
echo "Player ID: " . ($_SESSION['playerId'] ?? 'NOT SET') . "\n";
echo "Tutorial Session: " . ($_SESSION['tutorial_session_id'] ?? 'NOT SET') . "\n";
echo "Tutorial Player: " . ($_SESSION['tutorial_player_id'] ?? 'NOT SET') . "\n\n";

// Clear tutorial session variables
unset($_SESSION['tutorial_session_id']);
unset($_SESSION['tutorial_player_id']);

// Check if there's a real player ID stored somewhere
require_once(__DIR__ . '/config/constants.php');
require_once(__DIR__ . '/config/db_constants.php');
require_once(__DIR__ . '/config/bootstrap.php');

use Doctrine\DBAL\DriverManager;

$conn = DriverManager::getConnection([
    'dbname' => DB_CONSTANTS['dbname'],
    'user' => DB_CONSTANTS['user'],
    'password' => DB_CONSTANTS['password'],
    'host' => str_replace(':3306', '', DB_CONSTANTS['host']),
    'driver' => 'pdo_mysql',
]);

// Get the real player ID for this tutorial player
$tutorialPlayerId = $_SESSION['playerId'] ?? null;
if ($tutorialPlayerId) {
    // Phase 4.5: link is on players.real_player_id_ref.
    $result = $conn->fetchAssociative(
        "SELECT p.real_player_id_ref AS real_player_id
         FROM tutorial_players tp
         JOIN players p ON p.id = tp.player_id
         WHERE tp.player_id = ?",
        [$tutorialPlayerId]
    );

    if ($result && $result['real_player_id']) {
        $realPlayerId = $result['real_player_id'];
        $_SESSION['playerId'] = $realPlayerId;
        echo "✅ Switched back to real player ID: $realPlayerId\n";
        echo "\nRedirecting to main page in 2 seconds...\n";
        header("refresh:2;url=index.php");
    } else {
        echo "❌ Could not find real player ID for tutorial player $tutorialPlayerId\n";
    }
} else {
    echo "❌ No player ID in session\n";
}
