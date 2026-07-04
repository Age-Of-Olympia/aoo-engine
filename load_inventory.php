<?php
use App\View\Inventory\BankView;
use App\View\Inventory\CraftView;
use App\View\Inventory\InventoryView;
use Classes\Market;

require_once('config.php');

/*
 * Fragment inventaire / artisanat / banque pour les panneaux
 * glissants du HUD (js/hud.js). Reprend les branches d'inventory.php
 * sans l'enveloppe Ui ni la rangée d'onglets : Inventaire, Artisanat
 * et Banque sont des entrées du rail, chacune dans son panneau.
 */

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
