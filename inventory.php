<?php

require_once('config.php');



if(!empty($_POST['action']) && $_POST['action'] == 'store'){

    include('scripts/merchant/bank.php');

    exit();
}


$ui = new Ui('Inventaire');




echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a><a href="inventory.php"><button> Inventaire</button></a><a href="index.php?artisanat"><button><span class="ra ra-forging"></span> Artisanat</button></a><a href="inventory.php?bank"><button><span class="ra ra-gold-bar"></span></span> Banque</button></a></div>';


if(isset($_GET['bank'])){


    $player = new Player($_SESSION['playerId']);

    $market = new Market($player);

    include('scripts/merchant/bank.php');

    exit();
}


include('scripts/inventory.php');
