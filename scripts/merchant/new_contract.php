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
    InventoryView::renderInventory(itemsFromBank:true);
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


    echo '<div id="ajax-data"></div>';

    ?>
    <script>
    window.targetId = <?php echo $target->id ?>;
    </script>
    <script src="js/new_contract.js?20250516"></script>
    <?php


    echo Str::minify(ob_get_clean());
}


