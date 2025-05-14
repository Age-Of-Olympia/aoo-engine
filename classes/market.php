<?php

class Market{


    private $bids=null; // offres
    private $asks=null; // demandes
    private $target; // le marchand


    function __construct($target){


        $this->target = $target;
    }

    public function HasTarget(){
        return $this->target != null;
    }

    public function get($table){

        if($this->$table != null){

            return $this->$table;
        }
        if($table != 'bids' && $table != 'asks'){

            exit('error table');
        }

        $return = array();

        $order = ($table == 'bids') ? 'DESC' : 'ASC';

        $sql = '
        SELECT
        *
        FROM
        items_'. $table .'
        ORDER BY
        price
        '. $order .'
        ';

        $db = new Db();

        $res = $db->exe($sql);

        while($row = $res->fetch_object()){


            if(!isset($return[$row->item_id])){

                $return[$row->item_id] = array();
            }

            $return[$row->item_id][] = $row;
        }

        $this->$table = $return;

        return $return;
    }


    public function print_market($table, $player_id){


        ob_start();


        echo '
        <table border="1" align="center" class="marbre">
        <tr>
            <th></th>
            <th>Objet</th>
            <th>Meilleur prix</th>
            <th></th>
        </tr>
        ';


        foreach($this->get($table) as $k=>$e){


            $row = array_pop($e);


            $item = new Item($row->item_id);
            $item->get_data();


            echo '
            <tr
                class="item '. $table .'"

                data-market="'. $table .'"
                data-name="'. $item->row->name .'"
                data-id="'. $item->id .'"
                >
                ';

                echo '
                <td>
                    <img src="'. $item->data->mini .'" />
                </td>
                ';

                echo '
                <td>';
                    //Is player having at least 1 offer?
                    echo ucfirst($item->data->name);
                    if (array_filter($this->$table[$k], fn($row) => $row->player_id == $player_id)) {
                        echo '<b>*</b>';
                    }
                echo '
                </td>
                ';

                echo '
                <td>
                    '. $row->price .'Po
                </td>
                ';

                echo '
                <td>
                    <a href="merchant.php?'. $table .'&targetId='. $this->target->id .'&itemId='. $item->id .'">
                        Négocier
                    </a>
                </td>
                ';

                
            echo '
            </tr>
            ';
        }

        echo '
        </table>
        ';

        return ob_get_clean();
    }


    public function print_detail($item, $table, $player_id){


        ob_start();



        if(!isset($this->$table[$item->id])){

            exit('<div>'. ($table == 'bids') ? 'Acheter' : 'Vendre' .' cet objet: aucun contrat trouvé.</div>');
        }


        echo '
        <table border="1" align="center" class="marbre">
        <tr>
            <th></th>
            <th>Prix</th>
            <th>Nombre</th>
            <th>Origine</th>
            <th>Action</th>
        </tr>
        ';

        $data = $this->$table[$item->id];

        krsort($data);

        foreach($data as $k=>$row){


            if($k == 0) $color = 'red';
            elseif($k == count($data)-1) $color = 'blue';
            else $color = '';


            $playerJson = json()->decode('players', $row->player_id);

            $action = ($table == 'bids') ? 'Acheter' : 'Vendre';

            if($playerJson->id==$player_id){
                $action = ($table == 'bids') ? 'Annuler l\'offre' : 'Annuler la demande';
            }

            echo '
            <tr>
                ';


                echo '
                <td>
                    <img src="'. $item->data->mini .'" width="25" />
                </td>
                ';

                echo '
                <td>
                    <font color="'. $color .'">'. $row->price .'Po</font>
                </td>
                ';

                echo '
                <td>
                    x'. $row->stock .'</font>
                </td>
                ';

                echo '
                <td>
                    '. $playerJson->race .'</font>
                </td>
                ';

                echo '
                <td>
                    <button
                        class="action"

                        data-item="'. $item->id .'"
                        data-name="'. ucfirst($item->data->name) .'"
                        data-action="'. $action .'"
                        data-stock="'. $row->stock .'"
                        data-price="'. $row->price .'"
                        data-id="'. $row->id .'"

                        >'. $action .'</button>';



                echo '
                </td>
                ';

                echo '
            </tr>
            ';
        }

        echo '
        </table>
        ';


        ?>
        <script>
        $(document).ready(function(){

            $('.action').click(function(e){

                var item = $(this).data('name');
                var action = $(this).data('action');
                var stock = $(this).data('stock');
                var price = $(this).data('price');
                var id = $(this).data('id');

                if(action.startsWith('Annuler')){
                  window.location.href= 'merchant.php?<?php echo $table ?>&cancel&targetId=<?php echo $this->target->id ?>&id='+id;
                  return; 
                }

                var n = prompt('Combien?', stock);

                if(n == null || n == '' || n < 1 || n > stock){

                    return false;
                }
                total = n * price;


                if(confirm(action +' '+ item +' x'+ n +'\nà '+ price +'Po/unité\npour un total de '+ total +'Po?')){

                    $.ajax({
                        type: "POST",
                        url: 'merchant.php?targetId=<?php echo $this->target->id ?>&<?php echo $table ?>',
                        data: {'id': id, 'n': n}, // serializes the form's elements.
                        success: function(data)
                        {
                            // alert(data);
                            $dataHtml = $('<div>');
                            $dataHtml.html(data);
                            if($dataHtml.find('#error')[0] != null){
                                alert($dataHtml.find('#error').text());
                            }
                            else{
                                alert('Transaction réussie!\nLes objets ou l\'or ont été déposés sur votre compte en banque.');
                            }
                            document.location.reload();
                        }
                    });
                }
            });
        });
        </script>
        <?php

        return ob_get_clean();
    }


    public function print_bank($player){


        ob_start();


        $itemList = Item::get_item_list($player, $bank=true);


        echo Ui::print_inventory($itemList);


        return ob_get_clean();
    }


    public function perform($player, $table, $id, $n){


        // check transaction
        if(!is_numeric($id)){

            exit('error id');
        }

        if(!is_numeric($n) || $n < 1){

            exit('error id');
        }

        $db = new Db();

        $res = $db->get_single('items_'. $table, $id);

        if(!$res->num_rows){


            exit('error '. $table);
        }


        $row = $res->fetch_object();


        if($n > $row->stock){


            exit('<div id="error">Erreur de stock.</div>');
        }


        // total cost
        $total = $n * $row->price;


        if($table == 'asks'){


            // player sells item to target


            // transfer item to target bank
            $target = new Player($row->player_id);

            $item = new Item($row->item_id);

            if(!$item->give_item($player, $target, $n, $bank=true)){


                exit('<div id="error">Pas assez de cet objet.</div>');
            }


            // transfer gold to player bank
            $gold = Item::get_item_by_name('or');

            $gold->add_item($player, $total, $bank=true);
        }

        elseif($table == 'bids'){


            // player buys item from target


            // transfer gold to target bank
            $target = new Player($row->player_id);

            $gold = Item::get_item_by_name('or');

            if(!$gold->give_item($player, $target, $total)){


                exit('<div id="error">Pas assez d\'Or.</div>');
            }


            // transfer item to player bank

            $item = new Item($row->item_id);

            $item->add_item($player, $n, $bank=true);
        }


        $sql = 'UPDATE items_'. $table .' SET stock = stock - ? WHERE id=?';

        $db->exe($sql, array($n, $row->id));


        $values = array('stock'=>0);

        $db->delete('items_'. $table, $values);
    }

    public function cancel($table, $id, $player){
       
        $db = new Db();

        
        if($table == 'bids'){

            $sql = '
                SELECT
                *
                FROM
                items_bids
                where id = ?
                and player_id = ?
                ';


                $res = $db->exe($sql,  array($id, $player->id));

                while($row = $res->fetch_object()){

                    //give back items
                    $item = new Item($row->item_id);
        
                    $item->add_item($player, $row->stock, $bank=false);
                    
                }
        }

        if($table == 'asks'){
            $sql = '
                SELECT
                *
                FROM
                items_asks
                where id = ?
                and player_id = ?
                ';


            $res = $db->exe($sql,  array($id, $player->id));

            while($row = $res->fetch_object()){

                //give back gold
                $gold = Item::get_item_by_name('or');
                $gold->add_item($player, $row->stock*$row->price);
            }

        }

        $values = array('id'=>$id, 'player_id'=> $player->id);

        $db->delete('items_'. $table, $values);


    }
}
