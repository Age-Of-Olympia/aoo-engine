<?php
/**
 * Create tutorial map tiles
 *
 * This script creates a small 7x7 map for the tutorial
 * centered around (0,0) on the 'tutorial' plan
 */

define('NO_LOGIN', true);
require_once __DIR__ . '/../config.php';

$db = new Classes\Db();

echo "Creating tutorial map...\n";

// Create 7x7 grid centered at (0,0)
// From (-3,-3) to (3,3)
$tilesCreated = 0;

for ($x = -3; $x <= 3; $x++) {
    for ($y = -3; $y <= 3; $y++) {
        // Check if tile already exists
        $sql = 'SELECT id FROM coords WHERE x = ? AND y = ? AND z = 0 AND plan = "tutorial"';
        $result = $db->exe($sql, [$x, $y]);

        if ($result->num_rows == 0) {
            // Create new tile
            $sql = 'INSERT INTO coords (x, y, z, plan) VALUES (?, ?, 0, "tutorial")';
            $db->exe($sql, [$x, $y]);
            $tilesCreated++;
        }
    }
}

echo "Tutorial map created!\n";
echo "Tiles created: $tilesCreated\n";
echo "Total tiles: " . (7 * 7) . "\n";
echo "Map area: (-3,-3) to (3,3) on plan 'tutorial'\n";

// Verify
$sql = 'SELECT COUNT(*) as count FROM coords WHERE plan = "tutorial"';
$result = $db->exe($sql);
$row = $result->fetch_assoc();
echo "Total tutorial tiles in database: " . $row['count'] . "\n";
