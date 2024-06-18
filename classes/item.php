<?php

class Item{


    function __construct($itemId){

        $this->id = $itemId;

        $db = new Db();

        $res = $db->get_single('items', $this->id);

        $row = $res->fetch_object();

        $this->row = $row;
    }


    public function get_data(){


        $itemJson = json()->decode('items', $this->row->name);


        // first player json
        if(!$itemJson){


            $dir = ($this->row->private) ? 'private' : 'public';

            $path = 'datas/'. $dir .'/items/'. $this->row->name .'.json';


            $this->row->price = 1;
            $this->row->text = "Description de l'objet.";


            $data = Json::encode($this->row);

            Json::write_json($path, $data);

            $itemJson = json()->decode('items', $this->row->name);
        }


        $itemJson->img = (!empty($itemJson->img)) ? $itemJson->img : 'img/items/'. $itemJson->name .'.png';

        $itemJson->mini = (!empty($itemJson->mini)) ? $itemJson->mini : 'img/items/'. $itemJson->name .'_mini.png';


        $this->data = $itemJson;

        return $itemJson;
    }


    public function add_item($player, $n, $bank=false){


        $bank = ($bank) ? '_bank' : '';

        // format player
        if(is_numeric($player)){

            $player = new Player($player);
        }


        // enougth?
        if($n < 0 && $this->get_n($player) < $n){

            // not enougth

            return false;
        }


        // error n
        if(!is_numeric($n) || $n == 0){

            exit('error n');
        }

        // insert or update
        $sql = '
        INSERT INTO
        players_items'. $bank .'
        (player_id, item_id, n)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
        n = n + VALUES(n);
        ';

        $db = new Db();

        $db->exe($sql, array($player->id, $this->id, $n));


        // delete if neg
        if($n < 0){


            $sql = '
            DELETE FROM
            players_items'. $bank .'
            WHERE
            player_id = ?
            AND
            n <= 0
            ';

            $db->exe($sql, $player->id);
        }

        return true;
    }


    public function get_n($player, $bank=false){


        $bank = ($bank) ? '_bank' : '';

        $playerId = (is_numeric($player)) ? $player : $player->id;

        $sql = '
        SELECT
        n
        FROM
        players_items'. $bank .'
        INNER JOIN
        items
        ON
        item_id = items.id
        WHERE
        player_id = ?
        AND
        name = ?
        ';

        $db = new Db();

        $res = $db->exe($sql, array($playerId, $this->row->name));

        if(!$res->num_rows){

            return 0;
        }

        $row = $res->fetch_object();

        return $row->n;
    }


    public function give_item($player, $target, $n, $bank=false){


        if($n < 1){

            exit('error n');
        }


        if(!$this->add_item($player, -$n)){

            return false;
        }

        $this->add_item($target, $n, $bank);

        return true;
    }


    // STATIC
    public static function put_item($name, $private=0) : int{


        $db = new Db();

        $values = array(
            'name'=>$name,
            'private'=>$private
        );

        $db->insert('items', $values);

        return $db->get_last_id('items');
    }


    public static function get_item_by_name($name, $bank=false){


        $bank = ($bank) ? '_bank' : '';

        $db = new Db();

        $sql = '
        SELECT id FROM items'. $bank .' WHERE name = ?
        ';

        $res = $db->exe($sql, $name);

        if(!$res->num_rows){

            return false;
        }

        $row = $res->fetch_object();

        return new Item($row->id);
    }


    public static function get_item_list($player, $bank=false) : array {


        $bank = ($bank) ? '_bank' : '';

        if(!is_numeric($player)){

            $playerId = $player->id;
        }
        else{

            $playerId = $player;
        }

        $return = array();


        // always define or
        $return['or'] = 0;


        $sql = '
        SELECT
        name,
        n
        FROM
        players_items'. $bank .'
        INNER JOIN
        items
        ON
        item_id = items.id
        WHERE
        player_id = ?
        ';

        $db = new Db();

        $res = $db->exe($sql, $playerId);

        while($row = $res->fetch_object()){

            $return[$row->name] = $row->n;
        }


        return $return;
    }
}
