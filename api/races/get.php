<?php
/**
 * API endpoint to get race data (public, no auth required)
 *
 * GET /api/races/get.php?name=nain
 * Returns: { "success": true, "race": { "name": "Nain", "mvt": 4, ... } }
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/functions.php';

header('Content-Type: application/json; charset=utf-8');

$raceName = $_GET['name'] ?? null;

if (!$raceName) {
    echo json_encode(['success' => false, 'error' => 'Missing race name parameter']);
    exit;
}

$raceName = strtolower($raceName);
$raceData = json()->decode('races', $raceName);

if (!$raceData) {
    echo json_encode(['success' => false, 'error' => 'Race not found: ' . $raceName]);
    exit;
}

echo json_encode([
    'success' => true,
    'race' => [
        'name' => $raceData->name ?? $raceName,
        'mvt' => (int)($raceData->mvt ?? 4),
        'pv' => (int)($raceData->pv ?? 50),
        'pa' => (int)($raceData->a ?? 2),
        'bgColor' => $raceData->bgColor ?? '#FFFFFF',
    ]
]);
