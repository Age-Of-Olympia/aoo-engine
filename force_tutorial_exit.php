<?php
/**
 * Emergency script to force exit tutorial mode
 * Visit this page to switch back to your real character
 */

session_start();

require_once(__DIR__ . '/config/constants.php');
require_once(__DIR__ . '/config/db_constants.php');
require_once(__DIR__ . '/config/bootstrap.php');

use Doctrine\DBAL\DriverManager;

echo "<h1>Emergency Tutorial Exit</h1>";

$conn = DriverManager::getConnection([
    'dbname' => DB_CONSTANTS['dbname'],
    'user' => DB_CONSTANTS['user'],
    'password' => DB_CONSTANTS['password'],
    'host' => str_replace(':3306', '', DB_CONSTANTS['host']),
    'driver' => 'pdo_mysql',
]);

echo "<p>Current session player ID: " . ($_SESSION['playerId'] ?? 'NOT SET') . "</p>";

// Get all tutorial players for the current session's real player
$currentPlayerId = $_SESSION['playerId'] ?? null;

if ($currentPlayerId) {
    // Check if current player is a tutorial character.
    // Phase 4.5: link is on players.real_player_id_ref.
    $result = $conn->fetchAssociative(
        "SELECT p.real_player_id_ref AS real_player_id
         FROM tutorial_players tp
         JOIN players p ON p.id = tp.player_id
         WHERE tp.player_id = ?",
        [$currentPlayerId]
    );

    if ($result) {
        $realPlayerId = $result['real_player_id'];
        $_SESSION['playerId'] = $realPlayerId;
        echo "<p><strong>✅ SUCCESS!</strong> Switched from tutorial player $currentPlayerId to real player $realPlayerId</p>";
        echo "<p><a href='index.php'>Click here to go to the main page</a></p>";
        echo "<script>setTimeout(() => window.location.href = 'index.php', 2000);</script>";
    } else {
        echo "<p>Player $currentPlayerId is not a tutorial character.</p>";
        echo "<p>Showing all tutorial players for debugging:</p>";

        $allTutorialPlayers = $conn->fetchAllAssociative(
            "SELECT tp.player_id, p.real_player_id_ref AS real_player_id
             FROM tutorial_players tp
             JOIN players p ON p.id = tp.player_id
             WHERE p.real_player_id_ref = ?
             ORDER BY tp.id DESC LIMIT 10",
            [$currentPlayerId]
        );

        echo "<ul>";
        foreach ($allTutorialPlayers as $tp) {
            echo "<li>Tutorial player: {$tp['player_id']} → Real player: {$tp['real_player_id']}</li>";
        }
        echo "</ul>";
    }
}
?>
