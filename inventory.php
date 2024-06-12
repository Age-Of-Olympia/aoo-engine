<?php

require_once('config.php');

$ui = new Ui('Inventaire');


$player = new Player($_SESSION['playerId']);


echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a><a href="index.php?artisanat"><button><span class="ra ra-forging"></span> Artisanat</button></a></div>';


echo Ui::print_inventory($itemList = Item::get_item_list($player->id));
