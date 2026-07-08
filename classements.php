<?php

use Classes\Ui;

define('NO_LOGIN', true);


require_once('config.php');


$ui = new Ui('Classements des joueurs');

include('scripts/classements/body.php');
