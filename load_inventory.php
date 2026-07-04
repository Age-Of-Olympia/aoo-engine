<?php
use App\View\Inventory\BankView;
use App\View\Inventory\CraftView;
use App\View\Inventory\InventoryView;
use Classes\Market;

require_once('config.php');

/*
 * Fragment inventaire / artisanat / banque pour le panneau glissant
 * du HUD (js/hud.js). Reprend les branches d'inventory.php sans
 * l'enveloppe Ui ; les liens internes gardent les URLs inventory.php*
 * (repli plein-page sans JS) et sont réécrits vers ce fragment par le
 * routeur de panneaux.
 */

echo '<div><a href="inventory.php"><button><span class="ra ra-key"></span> Inventaire</button></a>'
    . '<a href="inventory.php?craft"><button><span class="ra ra-forging"></span> Artisanat</button></a>'
    . '<a href="inventory.php?bank"><button><span class="ra ra-gold-bar"></span> Banque</button></a></div>';

if (isset($_GET['bank'])) {

    $market = new Market(null);

    BankView::renderBank($market);

    exit();
}

if (isset($_GET['craft'])) {

    CraftView::renderCraft();

    exit();
}

InventoryView::renderInventory(itemsFromBank: false);
