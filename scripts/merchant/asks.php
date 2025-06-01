<?php

ob_start();


if(!empty($_GET['itemId'])){


    $item = new Item($_GET['itemId']);
    $item->get_data();


    echo '<h1>'. ucfirst($item->data->name) .'</h1>';
    echo '<div><img src="'. $item->data->mini .'" /></div>';

    $sql = 'SELECT n FROM players_items WHERE item_id = ? AND player_id = ?';
    $db = new Db();
    $res = $db->exe($sql, array($item->id, $player->id));
    $inventaire = 0;
    $banque = 0;

    if($res->num_rows > 0) {
        $row = $res->fetch_object();
        $inventaire = $row->n;
    }

    $res = $db->exe('SELECT n FROM players_items_bank WHERE item_id = ? AND player_id = ?', array($item->id, $player->id));
    if($res->num_rows > 0) {
        $row = $res->fetch_object();
        $banque = $row->n;
    }

    if($inventaire == 0 && $banque == 0) {
        echo 'Vous n\'en possédez pas.';
    } else {
        echo 'Vous en possédez ';
        if($inventaire > 0) {
            echo '<span class="inventaire">' . $inventaire . '</span>';
            if($banque > 0) echo ' dans l\'inventaire et ';
        }
        if($banque > 0) {
            echo '<span class="banque">' . $banque . ' en banque</span>';
        }
        echo '.';
    }

    echo $market->print_detail($item, 'asks', $player);

    exit();
}


echo '<h1>Demandes d\'Achat</h1>';


if(isset($_GET['newContract'])){


    include('scripts/merchant/new_contract.php');

    exit();
}



echo '<div>Voici les objets que les autres personnages veulent acheter.<br /><font><b>Vendez vos objets ici.</b></font></div>';

echo $market->print_market('asks', $player->id) ;

echo '<br/><b>*</b> Certaines des propositions d\'achat sont les vôtres'
?>
<div class="button-container">
        <a href="merchant.php?targetId=<?php echo $target->id ?>&asks&newContract">
            <button class="buy-button"><span class="ra ra-scroll-unfurled"></span> Nouvelle demande d'Achat</button>
        </a>
    </div>
</div>

<div class="section">
    <div class="section-title">Offre de Vente</div>
    <div>Si l'objet que vous souhaitez <b>vendre</b> n'apparaît pas dans la liste, vous pouvez créer une nouvelle offre de vente:</div>

    <div class="button-container">
        <a href="merchant.php?targetId=<?php echo $target->id ?>&bids&newContract">
            <button class="sell-button"><span class="ra ra-gavel"></span> Nouvelle offre de Vente</button>
        </a>
    </div>
</div>
<?php

echo Str::minify(ob_get_clean());
