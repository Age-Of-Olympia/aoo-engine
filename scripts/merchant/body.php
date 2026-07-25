<?php
use Classes\Ui;
use Classes\Market;
use App\Factory\PlayerFactory;
use App\View\Inventory\InventoryView;
use App\View\Inventory\BankView;
use App\View\Merchant\AsksView;
use App\View\Merchant\BidsView;
use App\View\Merchant\ExchangesView;

/*
 * Corps de la page marchand, partagé entre la page complète
 * (merchant.php, enveloppe Ui) et le panneau glissant du HUD
 * (load_merchant.php). Les onglets restent des liens merchant.php :
 * le routeur de panneaux (js/hud.js) les réécrit en fragments.
 */

$player = PlayerFactory::active();

$player->get_data();


// target = merchant
if(!isset($_GET['targetId'])){

    exit('error no merchant');
}


$target = PlayerFactory::legacy($_GET['targetId']);

$marketAccessError = Market::CheckMarketAccess($player, $target);
if($marketAccessError !=null){

    exit($marketAccessError);
}


// menu
if(!isset($_GET['hideMenu'])){

    echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a><a href="merchant.php?targetId='. $target->id .'"><button><span class="ra ra-speech-bubbles"></span> </button></a><a href="merchant.php?targetId='. $target->id .'&bids"><button class="sell-button"><span class="ra ra-gavel"></span> Offres de Vente</button></a><a href="merchant.php?targetId='. $target->id .'&asks"><button class="buy-button"><span class="ra ra-scroll-unfurled"></span> Demandes d\'Achat</button></a><a href="merchant.php?targetId='. $target->id .'&exchanges"><button class="exchange-button"><span class="ra ra-x-mark"></span> Echanges</button></a><a href="merchant.php?targetId='. $target->id .'&bank"><button><span class="ra ra-gold-bar"></span> Banque</button></a><a href="merchant.php?targetId='. $target->id .'&inventory"><button><span class="ra ra-key"></span> Inventaire</button></a></div>';
}


// market
$market = new Market($target);


if(isset($_GET['bids'])){
    BidsView::renderBids($player,$market,$target);
}
elseif(isset($_GET['asks'])){
    AsksView::renderAsks($player,$market,$target);
}
elseif(isset($_GET['exchanges'])){
    ExchangesView::renderExchanges($player,$target);
}
elseif(isset($_GET['bank'])){

    BankView::renderBank($market,$target);
}
elseif(isset($_GET['inventory'])){


    ?>
    <script>
    $(document).ready(function(e){

        var $actions = $('.preview-action');

        $actions
        .append('<button class="action" data-action="store">→Banque</button><br />');
    });
    </script>
    <?php

    InventoryView::renderInventory(itemsFromBank:false);
}
else{
    echo '<h1>Saruta & Frères</h1>
    Marchands d\'Olympia
    ';

    $player->get_data();


    $bg = 'img/dialogs/bg/'. $target->id .'.webp';

    if(!file_exists($bg)){

        $bg = 'img/dialogs/bg/marchand.webp';
    }


    $options = array(
        'name'=>$target->data->name,
        'avatar'=>$bg,
        'dialog'=>'marchand',
        'text'=>'',
        'player'=>$player,
        'target'=>$target
    );

    echo Ui::get_dialog($player, $options);
}


?>
<script>
/* Lignes du marché : le reste de la ligne est cliquable, mais le lien
 * « Négocier » (Classes\Market::print_market) appartient au SEUL
 * routeur de liens de js/hud.js. Sans ce garde, les deux se
 * marchaient dessus : ce handler ouvrait le panneau et détachait le
 * lien du DOM, puis le routeur — ne retrouvant plus son panneau
 * parent — basculait en toggle et le refermait aussitôt (« Négocier
 * ne fait rien »).
 * Délégué namespacé et purgé : ce fragment est ré-exécuté à chaque
 * chargement de panneau, un délégué non purgé s'empilerait. */
$(document).off('click.marketRow').on('click.marketRow', 'tr.item[data-market]', function(e){

    if($(e.target).closest('a[href]').length){

        return;
    }

    var url = 'merchant.php?'+ $(this).data('market') +'&targetId='+ $(this).data('target') +'&itemId='+ $(this).data('id');

    /* HUD : la fiche de l'objet du marché s'ouvre dans le
     * panneau ; habillage hérité : pleine page. */
    if(window.hudOpenPanel){

        window.hudOpenPanel(url.replace('merchant.php', 'load_merchant.php'), 'Marchand');
        return;
    }

    document.location = url;
});
</script>
