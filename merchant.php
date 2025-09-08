<?php
use Classes\Ui;
use Classes\Player;
use Classes\Market;
use App\View\Inventory\InventoryView;
use App\View\Inventory\BankView;
use App\View\Merchant\AsksView;
use App\View\Merchant\BidsView;
use App\View\Merchant\ExchangesView;
use App\View\Merchant\SpellsView;

require_once('config.php');


$ui = new Ui('Marchander', true);


$player = new Player($_SESSION['playerId']);

$player->get_data();


// target = merchant
if(!isset($_GET['targetId'])){

    exit('error no merchant');
}


$target = new Player($_GET['targetId']);

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
elseif(isset($_GET['spells'])){
    SpellsView::renderSpells($player,$target);
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
$(document).ready(function(){

    $('.item').click(function(e){

        document.location = 'merchant.php?'+ $(this).data('market') +'&targetId=<?php echo $target->id ?>&itemId='+ $(this).data('id');
    });
});
</script>