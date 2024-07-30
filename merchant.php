<?php

require_once('config.php');


$ui = new Ui('Marchander', true);


$player = new Player($_SESSION['playerId']);

$player->get_data();


// target = merchant
if(!isset($_GET['targetId'])){

    exit('error merchant');
}

$target = new Player($_GET['targetId']);

// $target->get_data();


// distance
$distance = View::get_distance($player->get_coords(), $target->get_coords());

if($distance > 1){

    exit(ERROR_DISTANCE);
}


// menu
if(!isset($_GET['hideMenu'])){

    echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a><a href="merchant.php?targetId='. $target->id .'"><button><span class="ra ra-speech-bubbles"></span> </button></a><a href="merchant.php?targetId='. $target->id .'&bids"><button class="sell-button"><span class="ra ra-gavel"></span> Offres de Vente</button></a><a href="merchant.php?targetId='. $target->id .'&asks"><button class="buy-button"><span class="ra ra-scroll-unfurled"></span> Demandes d\'Achat</button></a><a href="merchant.php?targetId='. $target->id .'&exchanges"><button class="exchange-button"><span class="ra ra-x-mark"></span> Echanges</button></a><a href="merchant.php?targetId='. $target->id .'&bank"><button><span class="ra ra-gold-bar"></span> Banque</button></a><a href="merchant.php?targetId='. $target->id .'&inventory"><button><span class="ra ra-key"></span> Inventaire</button></a></div>';
}


// market
$market = new Market($target);


if(isset($_GET['bids'])){


    include('scripts/merchant/bids.php');
}


elseif(isset($_GET['asks'])){


    include('scripts/merchant/asks.php');
}

elseif(isset($_GET['exchanges'])){


    include('scripts/merchant/exchanges.php');
}

elseif(isset($_GET['bank'])){


    include('scripts/merchant/bank.php');
}

elseif(isset($_GET['spells'])){


    include('scripts/merchant/spells.php');
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


    include('scripts/inventory.php');
}
else{


    echo '<h1>Saruta & Frères</h1>
    Marchands d\'Olympia
    ';

    $player->get_data();


    $options = array(
        'name'=>$player->data->name,
        'avatar'=>'img/dialogs/bg/'. $target->id .'.webp',
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
