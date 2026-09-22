<?php
use Classes\Ui;
use Classes\Market;
use App\Factory\PlayerFactory;
use App\Service\Counter\CounterAccessService;
use App\Service\Counter\CounterCatalog;
use App\View\Inventory\InventoryView;
use App\View\Inventory\BankView;
use App\View\Merchant\AsksView;
use App\View\Merchant\BidsView;
use App\View\Merchant\ExchangesView;
use App\View\Merchant\AtelierView;

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

/* Chaque comptoir ne sert que SES onglets — ceux que son dialogue
 * mentionne : la banque dépose et retire, l'échoppe tient les étals,
 * chacune est sourde au comptoir de l'autre. Le menu suit, et la garde
 * refuse ce que le menu ne propose pas : l'URL directe ne sert à rien. */
$counters = new CounterAccessService();
$servedTabs = $counters->servedTabs((int) $target->id, CounterCatalog::MERCHANT);

$tab = '';
foreach (array_keys(CounterCatalog::tabs(CounterCatalog::MERCHANT)) as $candidate) {
    if (isset($_GET[$candidate])) {
        $tab = (string) $candidate;
        break;
    }
}

$accessError = $counters->check($player, $target, CounterCatalog::MERCHANT, $tab !== '' ? $tab : null);
if ($accessError !== null) {
    exit($accessError);
}


// menu
if(!isset($_GET['hideMenu'])){

    echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a><a href="merchant.php?targetId='. $target->id .'"><button><span class="ra ra-speech-bubbles"></span> </button></a>';

    foreach($servedTabs as $servedTab){
        echo '<a href="merchant.php?targetId='. $target->id .'&'. $servedTab .'">'
            . CounterCatalog::menuButton(CounterCatalog::MERCHANT, $servedTab) .'</a>';
    }

    echo '</div>';
}


// market
$market = new Market($target);


switch($tab){

    case 'bids':
        BidsView::renderBids($player, $market, $target);
        break;

    case 'asks':
        AsksView::renderAsks($player, $market, $target);
        break;

    case 'exchanges':
        ExchangesView::renderExchanges($player, $target);
        break;

    case 'bank':
        BankView::renderBank($market, $target);
        break;

    case 'repair':
    case 'recycle':
        AtelierView::render($target, $tab);
        break;

    case 'inventory':
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
        break;

    default:
        /* Le comptoir est un BÂTIMENT (la garde ne laisse passer que lui) :
         * son dialogue (buildings.dialog — échoppe, banque…) et son visuel. */
        $details = (new \App\Service\BuildingService())->getDetails((int) $target->id);
        $dialog = (string) $details?->getDialog();

        $player->get_data();


        $bg = 'img/dialogs/bg/'. $target->id .'.webp';

        if(!file_exists($bg)){

            /* Sprite du type, sinon le même repli « initiales dans un
             * cadre » que la fiche (un type sans visuel résout à ''). */
            $bg = \App\Service\BuildingService::resolveAvatar((string) ($target->data->race ?? ''));
            if ($bg === '') {
                $bg = \Classes\View::structureInitialsAvatar((string) $target->data->name);
            }
        }


        $options = array(
            'name'=>$target->data->name,
            'avatar'=>$bg,
            'dialog'=>$dialog,
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
