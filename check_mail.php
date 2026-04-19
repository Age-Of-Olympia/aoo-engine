<?php
use App\Factory\PlayerFactory;
use Classes\Db;

require_once('config.php');


$player = PlayerFactory::legacy($_SESSION['playerId']);

$db = new Db();


echo json_encode($player->get_new_mails(all:true));
