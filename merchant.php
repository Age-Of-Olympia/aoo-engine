<?php

require_once('config.php');


$ui = new Ui('Marchander');


$player = new Player($_SESSION['playerId']);


// target = merchant
if(!isset($_GET['targetId'])){

    exit('error merchant');
}

$target = new Player($_GET['targetId']);


// distance
$distance = View::get_distance($player->get_coords(), $target->get_coords());

if($distance > 1){

    exit(ERROR_DISTANCE);
}


// menu
echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a><a href="merchant.php?targetId='. $target->id .'&bids"><button><span class="ra ra-gavel"></span> Offres</button></a><a href="merchant.php?targetId='. $target->id .'&asks"><button><span class="ra ra-scroll-unfurled"></span> Demandes</button></a><a href="merchant.php?targetId='. $target->id .'&bank"><button><span class="ra ra-gold-bar"></span> Banque</button></a></div>';


// market
$market = new Market($target);


if(isset($_GET['bids'])){


    include('scripts/merchant/bids.php');
}


elseif(isset($_GET['asks'])){


    include('scripts/merchant/asks.php');
}

elseif(isset($_GET['bank'])){


    echo '<h1>Banque</h1>';


    echo $market->print_bank($player);


    ?>
    <script>
    $(document).ready(function(){

        var $actions = $('.preview-action');

        $actions
        .append('<button class="action" data-action="withdraw">Retirer</button><br />')
        .append('<button class="action" data-action="store">Déposer</button><br />');


    });
    </script>
    <?php
}


?>
<script>
$(document).ready(function(){

    $('.item').click(function(e){

        document.location = 'merchant.php?'+ $(this).data('market') +'&targetId=<?php echo $target->id ?>&item='+ $(this).data('name');
    });
});
</script>
