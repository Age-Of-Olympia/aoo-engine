<?php

require_once('config.php');


$player = new Player($_SESSION['playerId']);

$db = new Db();


echo json_encode($player->get_new_mails($all=true));
