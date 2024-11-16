<?php

if(isset($_GET['cancel']) && !empty($_GET['id']) ){
    if(isset($_GET['asks'])){
        $market->cancel('asks',$_GET['id'], $player);
        
        echo "La demande a été annulée.<br/>";
        echo '<div><a href="merchant.php?asks&targetId='.$_GET['targetId'].'"><button><span class="ra ra-sideswipe"> Retour</button></a></div>';
    }
    else if(isset($_GET['bids'])){
        $market->cancel('bids',$_GET['id'], $player);

        echo "L'offre a été annulée.<br/>";
        echo '<div><a href="merchant.php?bids&targetId='.$_GET['targetId'].'"><button><span class="ra ra-sideswipe"> Retour</button></a></div>';
    }


}