<?php
use App\Factory\PlayerFactory;
use Classes\Db;

// Reads the session only: its lock is released at once (config.php)
define('SESSION_READ_ONLY', true);
require_once('config.php');


$player = PlayerFactory::legacy($_SESSION['playerId']);

$db = new Db();


echo json_encode($player->get_new_mails(all:true));
