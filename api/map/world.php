<?php
header('Content-Type: image/png');

$cachePath = __DIR__ . '/../../cache/global_map.png';

if (!file_exists($cachePath) || time() - filemtime($cachePath) > 86400) {
    // If file doesn't exist or is older than 24 hours, generate new one
    require_once __DIR__ . '/../../scripts/generate_global_map.php';
}

readfile($cachePath);
