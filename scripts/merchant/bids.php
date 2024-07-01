<?php

if(!empty($_POST['id']) && !empty($_POST['n'])){


    $market->perform($player, 'bids', $_POST['id'], $_POST['n']);

    exit();
}


if(!empty($_GET['itemId'])){


    $item = new Item($_GET['itemId']);
    $item->get_data();


    echo '<h1>'. ucfirst($item->data->name) .'</h1>';

    echo '<div><img src="'. $item->data->mini .'" /></div>';


    echo '<div>Vous possédez '. $player->get_gold() .'Po</div>';


    echo '<div>Prix de base: '. $item->data->price .'Po</div>';


    echo '<script>window.basePrice = '. $item->data->price .';</script>';


    echo $market->print_detail($item, 'bids');


    exit();
}


echo '<h1>Offres de Vente</h1>';


if(isset($_GET['newContract'])){


    include('scripts/merchant/new_contract.php');

    exit();
}


echo '<div>Voici les objets que les autres personnages veulent vendre.<br /><font color="cyan">Achetez des objets ici.</font></div>';


echo $market->print_market('bids');


echo '<div><a href="merchant.php?targetId='. $target->id .'&bids&newContract"><button>Nouvelle offre de Vente</button></a></div>';
