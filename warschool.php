<?php
use Classes\Ui;

require_once('config.php');

/* Corps partagé avec le fragment HUD : scripts/warschool/body.php
 * (même découpage que classements.php / load_classements.php). */

$ui = new Ui('École de guerre', true);

include('scripts/warschool/body.php');
