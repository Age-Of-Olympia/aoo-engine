<?php
/**
 * Admin API : exporte les couches d'un plan pour l'extension Tiled.
 *
 * GET /api/admin/map/export.php?plan=arcadia
 * Auth : header X-AoO-Tiled-Token (voir config/tiled_constants.php)
 *
 * Réponse : { success, plan, tileSize, layers: {tiles: [...], walls: [...], ...},
 *             images: {"walls/arbre1": "img/walls/arbre1.png", ...} }
 *
 * map_items est volontairement absent : c'est de l'état runtime (objets au sol),
 * jamais authorable depuis l'éditeur.
 */

use Classes\Db;

require_once __DIR__ . '/../../../config/bootstrap.php';
require_once __DIR__ . '/../../../config/functions.php';

header('Content-Type: application/json; charset=utf-8');

$tiledConstants = __DIR__ . '/../../../config/tiled_constants.php';
if (file_exists($tiledConstants)) {
    require_once $tiledConstants;
}

$token = $_SERVER['HTTP_X_AOO_TILED_TOKEN'] ?? '';
if (!defined('TILED_ADMIN_TOKEN') || TILED_ADMIN_TOKEN === '' || !hash_equals(TILED_ADMIN_TOKEN, $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing X-AoO-Tiled-Token']);
    exit;
}

$plan = $_GET['plan'] ?? '';
if ($plan === '' || !preg_match('/^[a-z0-9_-]+$/i', $plan)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid plan parameter']);
    exit;
}

// Colonnes spécifiques exportées par couche, en plus de name/x/y
const LAYER_EXTRA_COLUMNS = [
    'tiles'       => ['foreground', 'player_id'],
    'routes'      => ['player_id'],
    'plants'      => ['params'],
    'walls'       => ['damages', 'player_id'],
    'elements'    => ['endTime'],
    'foregrounds' => [],
    'triggers'    => ['params'],
    'dialogs'     => ['params'],
];

const IMAGE_EXTENSIONS = ['png', 'webp', 'gif'];

$db = new Db();

$layers = [];
$images = [];
$count = 0;

foreach (LAYER_EXTRA_COLUMNS as $layer => $extraColumns) {

    $columns = 'm.name, c.x, c.y';
    foreach ($extraColumns as $column) {
        $columns .= ', m.`' . $column . '`';
    }

    $res = $db->exe(
        'SELECT ' . $columns . '
         FROM map_' . $layer . ' m
         JOIN coords c ON c.id = m.coords_id
         WHERE c.plan = ?
         ORDER BY c.y, c.x',
        [$plan]
    );

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $row['x'] = (int) $row['x'];
        $row['y'] = (int) $row['y'];
        $rows[] = $row;

        // Résout l'image de chaque nom distinct (même convention que la
        // palette de tiled.php : img/<couche>/<name>.<ext>)
        $imageKey = $layer . '/' . $row['name'];
        if (!array_key_exists($imageKey, $images)) {
            $images[$imageKey] = null;
            foreach (IMAGE_EXTENSIONS as $ext) {
                $candidate = 'img/' . $layer . '/' . $row['name'] . '.' . $ext;
                if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $candidate)) {
                    $images[$imageKey] = $candidate;
                    break;
                }
            }
        }
    }

    $layers[$layer] = $rows;
    $count += count($rows);
}

if ($count === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Unknown or empty plan: ' . $plan]);
    exit;
}

echo json_encode([
    'success'  => true,
    'plan'     => $plan,
    'tileSize' => 50,
    'layers'   => $layers,
    'images'   => $images,
]);
