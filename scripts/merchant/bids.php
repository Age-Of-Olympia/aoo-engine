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


echo '<div><p>Voici les objets que les autres personnages veulent vendre.<br /><b>Achetez des objets ici.</b></p></div>';


echo $market->print_market('bids');

?>
<div class="button-container">
        <a href="merchant.php?targetId=<?php echo $target->id ?>&bids&newContract">
            <button class="sell-button"><span class="ra ra-gavel"></span> Nouvelle offre de Vente</button>
        </a>
    </div>
</div>

<div class="section">
    <div class="section-title">Demande d'Achat</div>
    <div>Si l'objet que vous souhaitez <b>acheter</b> n'apparaît pas dans la liste, vous pouvez créer une nouvelle demande d'achat:</div>

    <div class="button-container">
        <a href="merchant.php?targetId=<?php echo $target->id ?>&asks&newContract">
            <button class="buy-button"><span class="ra ra-scroll-unfurled"></span> Nouvelle demande d'Achat</button>
        </a>
    </div>
</div>
