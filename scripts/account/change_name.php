<?php


if(!Str::check_name($_POST['changeName'])){

    exit('Nom invalide');
}


if($player->have_option('alreadyChanged')){

    exit('Already changed');
}


$sql = 'UPDATE players SET name = ? WHERE id = ?';

$db = new Db();

$db->exe($sql, array($_POST['changeName'], $player->id));

$player->refresh_data();


$player->add_option('alreadyChanged');


exit('Nom changé avec succès!');
