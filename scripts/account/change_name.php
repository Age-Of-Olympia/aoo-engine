<?php
use Classes\Str;
use Classes\Db;

if(!Str::check_name($_POST['changeName'])){

    exit('<div id="data">Nom invalide</div>');
}


if($player->have_option('alreadyChanged')){

    exit('<div id="data">Already changed</div>');
}


$sql = 'UPDATE players SET name = ? WHERE id = ?';

$db = new Db();

$db->exe($sql, array($_POST['changeName'], $player->id));

$player->refresh_data();


$player->add_option('alreadyChanged');


exit('<div id="data">Nom changé avec succès!</div>');
