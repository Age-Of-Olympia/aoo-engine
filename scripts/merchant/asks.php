<?php

if(!empty($_POST['id']) && !empty($_POST['n'])){


    $market->perform($player, 'asks', $_POST['id'], $_POST['n']);

    exit();
}


if(!empty($_GET['item'])){


    $item = Item::get_item_by_name($_GET['item']);
    $item->get_data();


    echo '<h1>'. ucfirst($item->data->name) .'</h1>';

    echo '<div><img src="'. $item->data->mini .'" /></div>';


    $sql = 'SELECT n FROM players_items WHERE item_id = ? AND player_id = ?';

    $db = new Db();

    $res = $db->exe($sql, array($item->id, $player->id));


    if(!$res->num_rows){

        echo 'Vous n\'en possédez pas.';
    }
    else{

        $row = $res->fetch_object();

        echo 'Vous en possédez '. $row->n .'.';
    }


    echo $market->print_detail($item, 'asks');

    exit();
}


echo '<h1>Demandes</h1>';

echo '<div>Vous pouvez <b>vendre</b> ces objets sur le Marché.<br />
Cliquez sur l\'un d\'eux pour voir le détail de la demande.</div>';

echo $market->print_market('asks');
