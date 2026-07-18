<?php
require_once(__DIR__.'/config.php');

$file = 'datas/private/players/'. $_SESSION['playerId'] .'.svg';
if (file_exists($file)) {
    unlink($file);
}

exit('Vue rafraichie!');
