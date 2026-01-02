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

// Create 9x9 grid centered at (0,0)
// From (-4,-4) to (4,4) - 7x7 playable area with 1-tile wall border
$tilesCreated = 0;

for ($x = -4; $x <= 4; $x++) {
    for ($y = -4; $y <= 4; $y++) {
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
echo "Total tiles: " . (9 * 9) . "\n";
echo "Map area: (-4,-4) to (4,4) on plan 'tutorial'\n";

// Add walls around perimeter
echo "\nAdding walls...\n";
$wallsCreated = 0;

// Border walls and tree
$walls = [
    // North and South borders (y = -4 and y = 4)
    [-4, -4, 'mur_pierre'], [-3, -4, 'mur_pierre'], [-2, -4, 'mur_pierre'], [-1, -4, 'mur_pierre'],
    [0, -4, 'mur_pierre'], [1, -4, 'mur_pierre'], [2, -4, 'mur_pierre'], [3, -4, 'mur_pierre'], [4, -4, 'mur_pierre'],
    [-4, 4, 'mur_pierre'], [-3, 4, 'mur_pierre'], [-2, 4, 'mur_pierre'], [-1, 4, 'mur_pierre'],
    [0, 4, 'mur_pierre'], [1, 4, 'mur_pierre'], [2, 4, 'mur_pierre'], [3, 4, 'mur_pierre'], [4, 4, 'mur_pierre'],

    // West and East borders (x = -4 and x = 4, excluding corners already added)
    [-4, -3, 'mur_pierre'], [-4, -2, 'mur_pierre'], [-4, -1, 'mur_pierre'], [-4, 0, 'mur_pierre'],
    [-4, 1, 'mur_pierre'], [-4, 2, 'mur_pierre'], [-4, 3, 'mur_pierre'],
    [4, -3, 'mur_pierre'], [4, -2, 'mur_pierre'], [4, -1, 'mur_pierre'], [4, 0, 'mur_pierre'],
    [4, 1, 'mur_pierre'], [4, 2, 'mur_pierre'], [4, 3, 'mur_pierre'],

    // Gatherable tree for resource tutorial
    [0, 1, 'arbre1'] // -1 damages = gatherable
];

foreach ($walls as list($x, $y, $wallName)) {
    $damages = ($wallName === 'arbre1') ? -1 : 0;

    // Get coords_id
    $sql = 'SELECT id FROM coords WHERE x = ? AND y = ? AND z = 0 AND plan = "tutorial"';
    $result = $db->exe($sql, [$x, $y]);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $coordsId = $row['id'];

        // Check if wall already exists
        $sql = 'SELECT id FROM map_walls WHERE coords_id = ? AND name = ?';
        $result = $db->exe($sql, [$coordsId, $wallName]);

        if ($result->num_rows == 0) {
            $sql = 'INSERT INTO map_walls (name, coords_id, damages) VALUES (?, ?, ?)';
            $db->exe($sql, [$wallName, $coordsId, $damages]);
            $wallsCreated++;
        }
    }
}

echo "Walls created: $wallsCreated\n";

// Verify
$sql = 'SELECT COUNT(*) as count FROM coords WHERE plan = "tutorial"';
$result = $db->exe($sql);
$row = $result->fetch_assoc();
echo "Total tutorial tiles in database: " . $row['count'] . "\n";
