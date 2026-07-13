<?php

use Classes\Ui;

require_once('config.php');

$ui = new Ui('Personnages secondaires');

echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';

include('scripts/pnjs/body.php');
