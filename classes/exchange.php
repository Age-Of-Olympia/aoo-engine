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


    public function __construct($id = null) {
        $this->db = new Db();
        if ($id !== null) {
            $this->id = $id;
        }
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

    public function create($playerId,$targetId){

        $this->playerId = $playerId;
        $this->targetId = $targetId;

        $values = array(
            'player_id'=>$playerId,
            'target_id'=>$targetId,
            'update_time'=>time()
        );
        $this->db->insert('items_exchanges', $values);
        $this->id = $this->db->get_last_id('items_exchanges');

    }

    public function add_item_to_exchange($itemId, $n){
        $values = array(
            'exchange_id'=>$this->id,
            'item_id'=>$itemId,
            'n'=>$n,
            'player_id'=>$this->playerId,
            'target_id'=>$this->targetId
        );
        $this->db->insert('players_items_exchanges', $values);

    }

    public function accept_exchange(){
        $sql = '
        UPDATE
        items_exchanges
        SET
        target_ok = 1,
        player_ok = 1,
        update_time = ?
        WHERE
        id = ?
        ';
    
        $this->db->exe($sql, array(time(),$this->id));
    }

    public function refuse_exchange(){
        $sql = '
        UPDATE
        items_exchanges
        SET
        target_ok = -1,
        update_time = ?
        WHERE
        id = ?
        ';
    
        $this->db->exe($sql, array(time(),$this->id));
    }

    public function cancel_exchange(){
        $sql = '
        UPDATE
        items_exchanges
        SET
        player_ok = -1,
        update_time = ?
        WHERE
        id = ?
        ';
    
        $this->db->exe($sql, array(time(),$this->id));
    }

    public function give_items( $player ){
        foreach($this->items as $exchange_item){
            $item = new Item($exchange_item->item_id);
            $item->add_item($player, $exchange_item->n, true);
        }
    }

    public static function get_open_exchanges($playerId){

        $return = array();

        $sql = 'SELECT * FROM items_exchanges
         WHERE (player_id = ? or target_id = ?)
         AND  target_ok= 0 
         AND player_ok >=0
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
