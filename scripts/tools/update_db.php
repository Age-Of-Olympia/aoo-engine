<?php

use App\Service\DataBaseUpdateService;

require_once('config.php');

$player = new Player($_SESSION['playerId']);

include ('checks/super-admin-check.php');

$dbuService = new DataBaseUpdateService();

$dbuService->updateDb();
