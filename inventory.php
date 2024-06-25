<?php

require_once('config.php');



if(!empty($_POST['action']) && $_POST['action'] == 'store'){

    include('scripts/merchant/bank.php');

    exit();
}


$ui = new Ui('Inventaire');




echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a><a href="index.php?artisanat"><button><span class="ra ra-forging"></span> Artisanat</button></a></div>';


include('scripts/inventory.php');
