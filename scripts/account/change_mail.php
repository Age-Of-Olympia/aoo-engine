<?php

$plainMail = $_POST['changeMail'];

if(!Str::check_mail($plainMail)){

    exit('<div id="data">Mail invalide</div>');
}


$mail = password_hash( $plainMail, PASSWORD_DEFAULT );


$sql = 'UPDATE players SET mail = ?, plain_mail = ? WHERE id = ?';

$db = new Db();

$db->exe($sql, array($mail, $plainMail, $player->id));


exit('<div id="data">Mail changé avec succès!</div>');
