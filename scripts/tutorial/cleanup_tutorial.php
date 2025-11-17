<?php
/**
 * Cleanup tutorial data for a player
 * Usage: php scripts/tutorial/cleanup_tutorial.php <player_id>
 */

use Classes\Db;

define('NO_LOGIN', true);
require_once(__DIR__ . '/../../config.php');

if (!isset($argv[1])) {
    echo "Usage: php scripts/tutorial/cleanup_tutorial.php <player_id>\n";
    exit(1);
}

$playerId = (int) $argv[1];
$db = new Db();

echo "Cleaning up tutorial data for player ID {$playerId}...\n\n";

// 1. Find active tutorial sessions
$sql = 'SELECT tutorial_session_id FROM tutorial_progress WHERE player_id = ? AND completed = 0';
$result = $db->exe($sql, [$playerId]);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sessionId = $row['tutorial_session_id'];
        echo "Found active tutorial session: {$sessionId}\n";

        // Find tutorial player
        $tpSql = 'SELECT player_id FROM tutorial_players WHERE tutorial_session_id = ? AND is_active = 1';
        $tpResult = $db->exe($tpSql, [$sessionId]);

        if ($tpResult && $tpResult->num_rows > 0) {
            $tpRow = $tpResult->fetch_assoc();
            $tutorialPlayerId = $tpRow['player_id'];
            echo "  Tutorial player ID: {$tutorialPlayerId}\n";

            // Clear movement bonuses
            $clearSql = 'DELETE FROM players_bonus WHERE player_id = ? AND name = "mvt"';
            $db->exe($clearSql, [$tutorialPlayerId]);
            echo "  Cleared movement bonuses\n";

            // Clear turn data
            $clearTurnSql = 'DELETE FROM players_bonus WHERE player_id = ?';
            $db->exe($clearTurnSql, [$tutorialPlayerId]);
            echo "  Cleared all player bonuses\n";

            // Mark tutorial player as inactive
            $deactivateSql = 'UPDATE tutorial_players SET is_active = 0, deleted_at = NOW() WHERE player_id = ?';
            $db->exe($deactivateSql, [$tutorialPlayerId]);
            echo "  Deactivated tutorial player\n";
        }

        // Mark tutorial session as completed
        $completeSql = 'UPDATE tutorial_progress SET completed = 1, completed_at = NOW() WHERE tutorial_session_id = ?';
        $db->exe($completeSql, [$sessionId]);
        echo "  Marked tutorial session as completed\n";
    }
} else {
    echo "No active tutorial sessions found for player {$playerId}\n";
}

// Clear session variables (just informational - script can't modify web session)
echo "\nDone! Player can now start a fresh tutorial.\n";
echo "Note: Player should reload the page to clear session variables.\n";
