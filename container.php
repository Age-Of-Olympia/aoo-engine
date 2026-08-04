<?php

use Classes\Ui;

require_once('config.php');

if (empty($_GET['targetId'])) {
    exit('error container');
}

$ui = new Ui('Contenant');

echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"> Retour</button></a></div>';

include('scripts/container/body.php');
