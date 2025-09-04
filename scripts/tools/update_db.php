<?php
use Classes\Player;
use App\Service\DataBaseUpdateService;
use App\Service\AdminAuthorizationService;

require_once('config.php');

$player = new Player($_SESSION['playerId']);

AdminAuthorizationService::DoSuperAdminCheck();

$dbuService = new DataBaseUpdateService();

$dbuService->updateDb();
