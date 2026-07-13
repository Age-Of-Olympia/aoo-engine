<?php
use Classes\Ui;

require_once('config.php');

/* Corps partagé avec le fragment HUD : scripts/merchant/body.php
 * (même découpage que classements.php / load_classements.php). */

$ui = new Ui('Marchander', true);

include('scripts/merchant/body.php');
