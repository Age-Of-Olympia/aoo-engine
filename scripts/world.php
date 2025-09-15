<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db_constants_s2.php';

use App\Service\ViewService;

// Create database connection
$dsn = sprintf('mysql:host=%s;dbname=%s', 
    DB_CONSTANTS['host'], 
    DB_CONSTANTS['dbname']
);
$db = new PDO($dsn, DB_CONSTANTS['user'], DB_CONSTANTS['password']);

// Generate the map
$viewService = new ViewService($db);
$mapPath = $viewService->generateGlobalMap();

echo "Global map generated successfully at: ". serialize($mapPath) ."\n";
