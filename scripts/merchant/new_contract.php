<?php


if(!empty($_POST['action']) && !empty($_POST['itemId']) &&  !empty($_POST['n']) && !empty($_POST['price'])){

    $item = new Item($_POST['itemId']);
    $item->get_data();


    if(!empty($item->data->forbid->market)){

        exit('Impossible de créer un contrat sur cet objet.');
    }


    $values = array(
        'item_id'=>$item->id,
        'player_id'=>$player->id,
        'n'=>$_POST['n'],
        'price'=>$_POST['price'],
        'stock'=>$_POST['n']
    );

    $db = new Db();


    if(!is_numeric($_POST['price']) || $_POST['price'] < 1){

        exit('error price');
    }


    if($_POST['action'] == 'newBid'){


        $nMax = $item->get_n($player);


        if($_POST['n'] > $nMax){

            exit('Max. '. $nMax .'!');
        }

        $db->insert('items_bids', $values);


        $item->add_item($player, -$_POST['n']);

    }
    elseif($_POST['action'] == 'newAsk'){


        $total = $_POST['n'] * $_POST['price'];


        if($total > $player->get_gold()){


            exit('Vous ne possédez pas assez d\'Or pour prétendre acheter '. $_POST['n'] .' '. $item->row->name .'.');
        }


        $db->insert('items_asks', $values);

        //remove money to "block" it
        $gold = Item::get_item_by_name('or');
        $gold->add_item($player, -$total);
    }

    exit('new offer done');
}


if(isset($_GET['bids'])){

    ?>
    <script>
    $(document).ready(function(e){

        $('.preview-action')
        .append('<button class="action" data-action="newBid">Vendre</button><br />');
    });
    </script>
    <?php


    include('scripts/inventory.php');
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
    <script src="js/new_contract.js?20240907"></script>
    <?php


    echo Str::minify(ob_get_clean());
}


