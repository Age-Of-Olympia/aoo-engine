<?php


if(!Str::check_mail($_POST['changeMail'])){

    exit('<div id="data">Mail invalide</div>');
}


$mail = password_hash( $_POST['changeMail'], PASSWORD_DEFAULT );


$sql = 'UPDATE players SET mail = ? WHERE id = ?';

$db = new Db();

$db->exe($sql, array($mail, $player->id));


exit('<div id="data">Mail changé avec succès!</div>');
