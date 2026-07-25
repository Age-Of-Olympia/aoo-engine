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
    /* Exemplaires individualisés COMPRIS : ils étaient écartés de cette
     * liste faute de savoir les vendre — un pis-aller que la vente par
     * référence rend inutile. Chaque ligne affiche son usure, et le
     * serveur met en vente l'exemplaire cliqué, pas une unité de pile. */
    InventoryView::renderInventory(itemsFromBank: true);
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


