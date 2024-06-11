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

        $this->data = $itemJson;

        return $itemJson;
    }


    public function add_item($player, $n){


        // format player
        if(is_numeric($player)){

            $player = new Player($player);
        }

        // error n
        if(!is_numeric($n) || $n == 0){

            $n = 1;
        }

        // insert or update
        $sql = '
        INSERT INTO
        players_items
        (player_id, item_id, n)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
        n = n + VALUES(n);
        ';

        $db = new Db();

        $db->exe($sql, array(&$player->id, &$this->id, &$n));


        // delete if neg
        if($n < 0){


            $sql = '
            DELETE FROM
            players_items
            WHERE
            player_id = ?
            AND
            n <= 0
            ';

            $db->exe($sql, $player->id);
        }
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

    public static function get_item_by_name($name){


        $db = new Db();

        $sql = '
        SELECT id FROM items WHERE name = ?
        ';

        $res = $db->exe($sql, $name);

        if(!$res->num_rows){

            return false;
        }

        $row = $res->fetch_object();

        return new Item($row->id);
    }

    public static function get_player_by_name($name){


        $db = new Db();

        $sql = '
        SELECT id FROM items WHERE name = ?
        ';

        $res = $db->exe($sql, $name);

        if(!$res->num_rows){

            return false;
        }

        $row = $res->fetch_object();

        return new Item($row->id);
    }

    public static function get_item_list($playerId) : array {


        if(!is_numeric($playerId)){

            $playerId = $player->id;
        }

        $return = array();


        $sql = '
        SELECT
        name,
        n
        FROM
        players_items
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
