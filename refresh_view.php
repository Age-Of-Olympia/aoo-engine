<?php
session_start();


@unlink('datas/private/players/'. $_SESSION['playerId'] .'.svg');

exit('Vue rafraichie!');
