<?php

class Exchange{

    public Db $db;

    public int $id;
    public int $playerId;
    public int $targetId;
    public int $targetOk=0;
    public int $playerOk=0;
    public $updateTime;

    public $items = [];


    public function __construct($id = null) {
        $this->db = new Db();
        if ($id !== null) {
            if(is_numeric($id))
                $this->id = $id;
            else
                $this->id = -1;
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
            $this->playerOk = $row->player_ok;
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

    public function add_item_to_exchange($itemId, $n, $itemOwnerId){
        $this->refuse_exchange(true,true);

        $values = array(
            'exchange_id'=>$this->id,
            'item_id'=>$itemId,
            'n'=>$n,
            'player_id'=>$itemOwnerId == $this->playerId ? $this->playerId : $this->targetId,
            'target_id'=>$itemOwnerId == $this->playerId ? $this->targetId : $this->playerId
        );
        $this->db->insert('players_items_exchanges', $values);
    }

    public function remove_item_from_exchange($item_exchange_id,$itemId,$itemN, $itemOwnerId){
        $this->refuse_exchange(true,true);
        $sql = '
        DELETE FROM
        players_items_exchanges
        WHERE
        exchange_id = ?
        AND
        item_id = ?
        AND
        n = ?
        AND
        player_id = ?
        ';
    
        $this->db->exe($sql, array($this->id,$itemId,$itemN,$itemOwnerId));
    }

    public function is_in_progress()
    {
        return $this->id>0 && ($this->playerOk==0 || $this->targetOk==0);
    }
    
    public function accept_exchange($Istarget){
        $sql = '
        UPDATE
        items_exchanges
        SET ';
        $sql.=$Istarget ?'target_ok = 1, ' :'player_ok = 1, ';
        $sql.='update_time = ?
        WHERE
        id = ?
        ';
        if($Istarget)
        {
            $this->targetOk=1;
        } 
        else
        {
            $this->playerOk=1;
        }
        $this->db->exe($sql, array(time(),$this->id));
    }

    public function refuse_exchange($Istarget,$IsPlayer){
        $editNeeded = $Istarget && $this->targetOk==1;
        $editNeeded = $editNeeded || ($IsPlayer && $this->playerOk==1);
        if(!$editNeeded){
            return;
        }
        $sql = '
        UPDATE
        items_exchanges
        SET ';
        if($Istarget)
            $sql.='target_ok = 0, ';
        if($IsPlayer)
            $sql.='player_ok = 0, ';
        $sql.='update_time = ?
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
        target_ok = -1,
        update_time = ?
        WHERE
        id = ?
        ';
    
        $this->db->exe($sql, array(time(),$this->id));
    }

    public function give_items( $from_player, $to_player ){
        foreach($this->items as $exchange_item){
           if($exchange_item->player_id != $from_player->id)continue;
            if($exchange_item->target_id != $to_player->id && $exchange_item->player_id != $to_player->id){
                throw new Exception('Player is not the target of the exchange');
                continue;
            }
            if($exchange_item->n < 0){
                throw new Exception('Negative item count');
                continue;
            }
            $item = new Item($exchange_item->item_id);
            $item->add_item($to_player, $exchange_item->n, true);
        }
    }

    public function render_items_for_player($playerId){
        $return = '';
        $noItem= true;
        foreach($this->items as $exchange_item){
            if($exchange_item->player_id != $playerId){
                continue;
            }
            $item = new Item($exchange_item->item_id);
            $item->get_data();
            $return .= '<li>'. $exchange_item->n . ' ' . $item->data->name. '</li>';;
            $noItem = false;
        }

        if($noItem){
            $return = '<li>Aucun objet</li>';
        }
        return $return;
    }

    public static function get_open_exchanges($playerId){

        $return = array();

        $sql = 'SELECT * FROM items_exchanges
         WHERE (player_id = ? or target_id = ?)
         AND (target_ok = 0 or player_ok = 0)
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