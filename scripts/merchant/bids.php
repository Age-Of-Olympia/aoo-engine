<?php
use Classes\Item;
use Classes\Str;
ob_start();


if(!empty($_GET['itemId'])){


    $item = new Item($_GET['itemId']);
    $item->get_data();


    echo '<h1>'. ucfirst($item->data->name) .'</h1>';

    echo '<div><img src="'. $item->data->mini .'" /></div>';


    $or_inventaire = $player->get_gold();
    $or_banque = $player->get_gold(true);
    echo '<div>Vous possédez <span class="or-inventaire">'. $or_inventaire .'</span> Po dans l\'inventaire';
    if ($or_banque > 0) {
        echo ' et <span class="or-banque">'. $or_banque .'</span> Po en banque';
    }
    echo '</div>';


    echo '<div>Prix de base: '. $item->data->price .'Po</div>';


    echo '<script>window.basePrice = '. $item->data->price .';</script>';


    echo $market->print_detail($item, 'bids',$player);


    exit();
}


echo '<h1>Offres de Vente</h1>';


  if(isset($_GET['newContract'])){


      include('scripts/merchant/new_contract.php');

      exit();
  }


echo '<div><p>Voici les objets que les autres personnages veulent vendre.<br /><b>Achetez des objets avec votre <i>or en banque</i>.</b></p></div>';


echo $market->print_market('bids',$player->id);

echo '<br/><b>*</b> Certains des objets en vente sont les vôtres';
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
<?php

echo Str::minify(ob_get_clean());
