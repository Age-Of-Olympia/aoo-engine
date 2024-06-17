<?php


if(!empty($_POST['action']) && !empty($_POST['item']) &&  !empty($_POST['n']) && !empty($_POST['price'])){

    $item = Item::get_item_by_name($_POST['item']);
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


    if($_POST['action'] == 'newAsk'){


        if($_POST['n'] > $itemList[$item->row->name]){

            exit('Max. '. $itemList[$item->row->name] .'!');
        }

        $db->insert('items_asks', $values);


        $item->add_item($player, -$_POST['n']);

    }
    elseif($_POST['action'] == 'newBid'){


        $total = $_POST['n'] * $_POST['price'];


        if($total > $itemList['or']){


            exit('Vous ne possédez pas assez d\'Or pour prétendre acheter '. $_POST['n'] .' '. $item->row->name .'.');
        }


        $db->insert('items_bids', $values);
    }

    exit();
}


if(isset($_GET['asks'])){

    ?>
    <script>
    $(document).ready(function(e){

        $('.preview-action')
        .append('<button class="action" data-action="newAsk">Vendre</button><br />');
    });
    </script>
    <?php


    include('scripts/inventory.php');
}

elseif(isset($_GET['bids'])){


    echo '
    <select id="item">
        ';

        $sql = 'SELECT * FROM items';

        $db = new Db();

        $res = $db->exe($sql);

        $itemList = array();

        while($row = $res->fetch_object()){


            $itemJson = json()->decode('items', $row->name);

            if(!empty($itemJson->forbid->market)){

                continue;
            }

            $itemList[$row->name] = $itemJson->name;

        }


        ksort($itemList);


        foreach($itemList as $k=>$e){


            echo '
            <option value="'. $k .'">'. ucfirst($e) .'</option>
            ';
        }

        echo '
    </select>
    ';


    echo '<button id="submit">Créer une Offre d\'achat</button>';


    echo '<div id="ajax-data"></div>';


    ?>
    <script>
    $(document).ready(function(){

        $('#item').change(function(e){

            var item = $(this).val();


            $.ajax({
                type: "POST",
                url: 'merchant.php?targetId=<?php echo $target->id ?>&bids&hideMenu&item='+ item,
                data: {}, // serializes the form's elements.
                success: function(data)
                {
                    // alert(data);
                    $('#ajax-data').html(data);
                }
            });
        });


        $('#submit').click(function(e){

            var n = prompt('Combien?', 1);

            if(n == null){

                return false;
            }

            if(n == '' || n < 1){

                alert('Nombre invalide!');
                return false;
            }
        });


    });
    </script>
    <?php
}


