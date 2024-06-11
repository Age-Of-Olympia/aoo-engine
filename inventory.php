<?php

require_once('config.php');


$player = new Player($_SESSION['playerId']);


echo Ui::get_inventory($player);
