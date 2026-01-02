<?php
/**
 * Clear all tutorial session variables
 */

session_start();

echo "<h1>Clear Tutorial Session</h1>";

echo "<p><strong>Before:</strong></p>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Clear ALL tutorial-related session variables
unset($_SESSION['tutorial_session_id']);
unset($_SESSION['tutorial_player_id']);
unset($_SESSION['in_tutorial']);
unset($_SESSION['tutorial_consume_movements']);

echo "<p><strong>After:</strong></p>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<p>✅ Tutorial session variables cleared!</p>";
echo "<p><a href='index.php'><strong>Click here to return to main game</strong></a></p>";
echo "<script>setTimeout(() => window.location.href = 'index.php', 2000);</script>";
?>
