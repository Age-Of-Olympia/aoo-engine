<?php

class Item{


    function __construct($itemId, $row=false){


        $this->id = $itemId;


        if(!$row){

            $db = new Db();

            $res = $db->get_single('items', $this->id);

            $row = $res->fetch_object();

        }


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


        $itemJson->img = (!empty($itemJson->img)) ? $itemJson->img : 'img/items/'. $this->row->name .'.webp';

        $itemJson->mini = (!empty($itemJson->mini)) ? $itemJson->mini : 'img/items/'. $this->row->name .'_mini.webp';

        $itemJson->name = ucfirst($itemJson->name);


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
        if($n < 0 && $this->get_n($player, $bank) + $n < 0){

            // not enougth
            return false;
        }


        // error n
        if(!is_numeric($n) || $n == 0){

            exit('error n '. $n);
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


        $player->refresh_invent();

        return true;
    }


    public function get_n($player, $bank=false, $equiped=false){


        $bank = ($bank) ? '_bank' : '';

        $equiped = ($equiped) ? 'AND equiped != ""' : '';

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
        '. $equiped .'
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


    public function is_crafted_with($ingredients){


        if(!is_array($ingredients)){


            $ingredients = array($ingredients);
        }

        $recipe = $this->get_recipe();

        foreach($ingredients as $e){


            if(!isset($recipe[$e])){

                return false;
            }
        }

        return true;
    }

    public function get_recipe() : array{


        $craftJson = json()->decode('', 'crafts');

        $return = array();

        foreach($craftJson as $occurrence){


            foreach($occurrence as $recipe){


                if($recipe->name != $this->row->name){

                    continue;
                }


                foreach($recipe->recette as $items){

                    $return[$items->name] = $items->n;
                }

                break;
            }
        }

        return $return;
    }


    public function get_version($options){


        $options = array_merge((array) $this->row, $options);


        $conditions = array(
            'name = "'. $this->row->name .'"',
            'private = '. $this->row->private
        );

        $newOptions = array(
            'name'=>$this->row->name,
            'private'=>$this->row->private
        );

        foreach(ITEMS_OPT as $k=>$e){


            $newOptions[$k] = $options[$k];
            $conditions[$k] = $k .' = "'. $options[$k] .'"';

            if(in_array($k, array('spell','blessed_by_id'))){


                if(empty($options[$k])){

                    unset($newOptions[$k]);
                    unset($conditions[$k]);
                }
            }
        }

        $db = new Db();

        $sql = '
        SELECT
        id
        FROM
        items
        WHERE
        '. implode(' AND ', $conditions) .'
        ';

        $res = $db->exe($sql);

        if($res->num_rows){


            $row = $res->fetch_object();

            $newId = $row->id;
        }
        else{


            $db->insert('items', $newOptions);

            $newId = $db->get_last_id('items');
        }

        return $newId;
    }


    // STATIC
    public static function put_item($name, $private=0, $options=false) : int{


        $db = new Db();

        $values = array(
            'name'=>strtolower($name),
            'private'=>$private
        );


        if($options && is_array($options)){


            $values = array_merge($values, $options);
        }


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


    public static function get_equiped_list($player) : array {


        return self::get_item_list($player, $bank=false, $equiped=true);
    }


    public static function get_item_list($player, $bank=false, $equiped=false) : array {


        $equipedOrder = 'equiped DESC,';

        if($bank){

            $bank = '_bank';
            $equipedOrder = '';
        }
        else{

            $bank = '';
        }


        if($equiped){

            $equiped = 'AND equiped != ""';
        }
        else{

            $equiped = '';
        }


        if(!is_numeric($player)){

            $playerId = $player->id;
        }
        else{

            $playerId = $player;
        }

        $return = array();


        $sql = '
        SELECT
        *
        FROM
        players_items'. $bank .'
        INNER JOIN
        items
        ON
        item_id = items.id
        WHERE
        player_id = ?
        '. $equiped .'
        ORDER BY
        '. $equipedOrder .' items.name
        ';

        $db = new Db();

        $res = $db->exe($sql, $playerId);

        while($row = $res->fetch_object()){


            $return[$row->id] = $row;
        }


        // or
        if(!isset($return[1]) && !$equiped){

            $return[1] = (object) array('id'=>1,'name'=>'or','price'=>1,'n'=>0, 'equiped'=>'');

            foreach(ITEMS_OPT as $k=>$e){

                $return[1]->$k = 0;
            }
        }


        return $return;
    }


    public static function get_formatted_name($name, $row){


        foreach(ITEMS_OPT as $k=>$e){
            if(!empty($row->$k)){ $name = $e . $name . $e; }
        }


        if(!empty($row->spell)){

            $spellJson = json()->decode('actions', $row->spell);

            $name .= '<br /><sup>'. $spellJson->name .'</sup>';
        }

        return $name;
    }


    public static function get_unformatted_name($name){


        foreach(ITEMS_OPT as $e){

            $name = str_replace($e, '', $name);
        }

        return $name;
    }


    // print item carac
    public static function get_item_carac($itemJson){


        $return = array();


        // spellMalus
        if(!empty($itemJson->spellMalus))
            $return[] = '<font color="red"><del>M</del></font>';


        // parchemin sort
        elseif(!empty($itemJson->spell)){


            // json
            $json = new Json();

            // spell Json
            $spellJson = $json->decode('spell', $itemJson->spell);

            // return spell name
            $return[] = '<font color="blue">'. $spellJson->name .'</font>';
        }


        // search for item bonus carac
        foreach(CARACS as $k=>$e){


            // special fF
            if($k == 'f' && !empty($itemJson->fixedF)){

                $return[] = '<font color="blue">'. $e .'='. $itemJson->fixedF .'</font>';
                continue;
            }

            // special mDamage
            if($k == 'f' && !empty($itemJson->mDamage)){

                $return[] = '<font color="blue">'. $e .'=M</font>';
                continue;
            }


            // item have not this bonus
            if(!isset($itemJson->$k))
                continue;


            // item have this bonus
            $carac = $itemJson->$k;


            // bonus blue or malus red
            if( $carac > 0 )
                $return[] = '<font color="blue">'. $e .'+'. $carac .'</font>';
            if( $carac < 0 )
                $return[] = '<font color="red">'. $e .''. $carac .'</font>';
        }


        // special demolition
        if(!empty($itemJson->demolition)){

            $return[] = '<font color="blue">démolition+'. $itemJson->demolition .'</font>';
        }

        // pr
        if(!empty($itemJson->pr)){
            $return[] = '<font color="blue">Pr+'. $itemJson->pr .'</font>';
        }

        return $return;
    }


    public static function get_free_emplacement($player) : int{


        $values = ITEM_EMPLACEMENT_FORMAT;


        foreach($values as $k=>$e){

            if(in_array($e, array('trophee','munition'))){

                unset($values[$k]);
            }
        }


        // count emplacements
        $sql = '
        SELECT COUNT(*) AS n
        FROM
        players_items
        WHERE
        player_id = ?
        AND
        equiped IN('. Db::print_in($values) .')
        ';

        $values = array_merge(array($player->id), $values);

        $db = new Db();

        $res = $db->exe($sql, $values);

        $row = $res->fetch_object();

        if($row->n >= ITEM_LIMIT){

            return 0;
        }

        return ITEM_LIMIT - $row->n;
    }


    public static function get_cost($costs){


        $return = array();

        foreach($costs as $k=>$e){

            $return[] = $e . CARACS[$k];
        }

        return $return;
    }
}
