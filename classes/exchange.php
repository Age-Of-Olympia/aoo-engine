<?php

class Exchange{

    private $db;

    public int $id;
    public int $playerId;
    public int $targetId;
    public bool $targetOk;
    public bool $playerOK;
    public $updateTime;

    public $items = [];


    function __construct(){
        $this->db = new Db();
    }

        public function get_base_data(){
        if (!isset($this->id) || $this->id == null)
            exit('get data impossible, relies on id');

        $sql = '
        SELECT player_id, target_id, player_ok, target_ok, update_time
        FROM items_exchanges
        where id = ?
        ';



        $res = $this->db->exe($sql, array($this->id));

        while($row = $res->fetch_object()){
            $this->playerId = $row->player_id;
            $this->targetId = $row->target_id;
            $this->playerOK = $row->player_ok;
            $this->targetOk = $row->target_ok;
            $this->updateTime = $row->update_time;
        }

    }

    public function get_items_data() {
        $sql = '
            SELECT exchange_id, item_id, n, player_id, target_id
            FROM players_items_exchanges
            WHERE exchange_id = ?
        ';


        $res = $this->db->exe($sql, array($this->id));

        while($row = $res->fetch_object()){
            $this->items[] = $row;
        }
    }

    public function get_last_data(){
        $sql = '
        SELECT items_exchanges.id, items_exchanges.player_ok, items_exchanges.target_ok, items_exchanges.update_time
        FROM items_exchanges
        where items_exchanges.player_id = ?
        and items_exchanges.target_id = ?
        and not (target_ok and player_ok)
        order by update_time desc
        limit 1
        ';



        $res = $this->db->exe($sql, array($this->playerId, $this->targetId));

        while($row = $res->fetch_object()){
            $this->id= $row->id;
            $this->playerOK = $row->player_ok;
            $this->targetOk = $row->target_ok;
            $this->updateTime = $row->update_time;
        }

    }
    public function create_and_get($playerId,$targetId){

        $this->playerId = $playerId;
        $this->targetId = $targetId;
        $this->get_last_data();
        if (!isset($this->id)){

            $values = array(
                'player_id'=>$playerId,
                'target_id'=>$targetId,
                'update_time'=>time()
            );
            $this->db->insert('items_exchanges', $values);

            $this->get_base_data();
        }
    }

    public function add_players_items_exchange($itemId, $n,$playerId,$targetId){
        $values = array(
            'exchange_id'=>$this->id,
            'item_id'=>$itemId,
            'n'=>$n,
            'player_id'=>$playerId,
            'target_id'=>$targetId
        );
        $this->db->insert('players_items_exchanges', $values);

    }


    public static function get_open_exchanges($playerId){

        $return = array();

        $sql = 'SELECT * FROM items_exchanges
         WHERE player_id = ? or target_id = ?
         AND not (player_ok and target_ok) 
         order by update_time desc';


        $db = new Db();
        $res = $db->exe($sql, array($playerId,$playerId));

        while($row = $res->fetch_object()){
            $exchange = new Exchange();
            $exchange->id = $row->id;
            $exchange->get_base_data();
            $exchange->get_items_data();
            $return[] = $exchange;
        }

        return $return;
    }

}
