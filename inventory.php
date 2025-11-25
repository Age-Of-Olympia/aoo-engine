<?php
use Classes\Ui;
use Classes\Market;
use App\View\Inventory\InventoryView;
use App\View\Inventory\BankView;
use App\View\Inventory\CraftView;
use App\View\TutorialView;
use App\Tutorial\TutorialHelper;
use Classes\Player;

require_once('config.php');


if(!empty($_POST['action']) && $_POST['action'] == 'store'){
    BankView::renderBank();
    exit();
}


$ui = new Ui('Inventaire');

// Load tutorial system if tutorial is active
$playerId = TutorialHelper::getActivePlayerId();
$player = new Player($playerId);
$player->get_data();
TutorialView::renderTutorial($player);

echo '<div><a href="index.php"><button id="back"><span class="ra ra-sideswipe"></span> Retour</button></a><a href="inventory.php"><button><span class="ra ra-key"></span> Inventaire</button></a><a href="inventory.php?craft"><button><span class="ra ra-forging"></span> Artisanat</button></a><a href="inventory.php?bank"><button><span class="ra ra-gold-bar"></span></span> Banque</button></a></div>';


if(isset($_GET['bank'])){

    $market = new Market(null);

    BankView::renderBank($market);

    exit();
}

if(isset($_GET['craft'])){


    CraftView::renderCraft();

    exit();
}
InventoryView::renderInventory(itemsFromBank:false);
