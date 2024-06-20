<?php

require_once('config.php');


$player = new Player($_SESSION['playerId']);

echo $player->get_new_mails();
