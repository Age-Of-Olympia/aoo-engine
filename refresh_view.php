<?php
session_start();

$file = 'datas/private/players/'. $_SESSION['playerId'] .'.svg';
if (file_exists($file)) {
    unlink($file); // Delete the file
}

exit('Vue rafraichie!');
