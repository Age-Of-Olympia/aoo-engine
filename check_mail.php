<?php
use Classes\ActorInterface;
use Classes\Db;

require_once('config.php');


$player = new ActorInterface($_SESSION['playerId']);

$db = new Db();


echo json_encode($player->get_new_mails(all:true));
