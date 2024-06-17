<?php

if(!empty($_POST['id']) && !empty($_POST['n'])){


    $market->perform($player, 'bids', $_POST['id'], $_POST['n']);

    exit();
}


if(!empty($_GET['item'])){


    $item = Item::get_item_by_name($_GET['item']);
    $item->get_data();


    echo '<h1>'. ucfirst($item->data->name) .'</h1>';

    echo '<div><img src="'. $item->data->mini .'" /></div>';


    echo 'Vous possédez '. $player->get_gold() .'Po';


    echo $market->print_detail($item, 'bids');

    exit();
}


echo '<h1>Offres (Acheter)</h1>';


if(isset($_GET['newContract'])){


    include('scripts/merchant/new_contract.php');

    exit();
}


echo '<div><a href="merchant.php?targetId='. $target->id .'&bids&newContract"><button>Acheter un objet qui n\'est pas listé</button></a></div>';

echo $market->print_market('bids');
