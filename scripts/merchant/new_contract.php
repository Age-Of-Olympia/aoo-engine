<?php
use Classes\Player;
use Classes\Item;
use Classes\Db;
use Classes\Str;
use App\View\Inventory\InventoryView;

if(isset($_GET['bids'])){

    ?>
    <script>
    $(document).ready(function(e){

        $('.preview-action')
        .append('<button class="action" data-action="newBid">Vendre</button><br />');
    });
    </script>
    <?php
    /* Piles seulement : le marché échange du fongible — une offre porte
     * un objet et une quantité, pas un individu. Les exemplaires
     * individualisés (usés, nommés, de qualité) sont visibles en banque
     * depuis qu'elle les accepte, mais les mettre en vente échouerait à
     * la première décrémentation : add_item ne sait pas débiter une
     * instance. Mieux vaut ne pas les proposer que promettre une vente
     * impossible. */
    echo '<p><small>Seules les piles sont vendables : un exemplaire usé,'
        . ' nommé ou de qualité ne s\'échange pas au marché.</small></p>';

    InventoryView::renderInventory(itemsFromBank: true, stacksOnly: true);
}

elseif(isset($_GET['asks'])){


    ob_start();

    echo '<div><p>Choisissez un objet que vous souhaitez Acheter.<br />Vous pourrez ensuite choisir le nombre d\'objet à acheter et fixer un prix.</p></div>';


    echo '
    <select id="item">
        ';

        echo '<option selected disabled>Choisissez un objet</option>';

        $sql = 'SELECT * FROM items GROUP BY name ORDER by name';

        $db = new Db();

        $res = $db->exe($sql);

        $itemList = array();

        while($row = $res->fetch_object()){


            $item = new Item($row->id, $row);
            $item->get_data();

            if(!empty($item->data->forbid->market)){

                continue;
            }

            $itemList[] = $item;

        }


        ksort($itemList);


        foreach($itemList as $item){


            echo '
            <option value="'. $item->id .'">'. ucfirst($item->data->name) .'</option>
            ';
        }

        echo '
    </select>
    ';


    echo '<button id="submit">Créer une Demande d\'Achat</button>';


    /* id propre : #ajax-data existe déjà dans le HUD (bandeau de
     * sélection) — un doublon détournait l'aperçu du contrat. */
    echo '<div id="contract-preview"></div>';

    ?>
    <script>
    window.targetId = <?php echo $target->id ?>;
    </script>
    <script src="js/new_contract.js?v=20260716"></script>
    <?php


    echo Str::minify(ob_get_clean());
}


