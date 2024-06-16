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


echo '<h1>Offres</h1>';

echo '<div>Vous pouvez <b>acheter</b> ces objets sur le Marché.<br />
Cliquez sur l\'un d\'eux pour voir le détail de l\'offre.</div>';

echo $market->print_market('bids');
