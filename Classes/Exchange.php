<?php
namespace Classes;

use Exception;

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
        /* id : la table n'avait AUCUNE clé primaire, si bien qu'on ne
         * savait viser une ligne que par ses valeurs — et supprimer donc
         * toutes les lignes identiques d'un coup. Invisible tant que
         * tout est fongible, bloquant dès que deux exemplaires du même
         * objet sont en jeu.
         *
         * L'état de l'exemplaire est LU ici, jamais recopié dans la
         * ligne d'échange : cette lecture alimente à la fois l'affichage
         * et la livraison, les deux doivent voir la même chose. LEFT
         * JOIN — une ligne de pile n'a pas d'instance. */
        $sql = '
            SELECT e.id, e.exchange_id, e.item_id, e.n, e.player_id, e.target_id, e.instance_id,
                   i.durability, i.durability_max, i.quality, i.custom_name, i.destroyed
            FROM players_items_exchanges e
            LEFT JOIN item_instances i ON i.id = e.instance_id
            WHERE e.exchange_id = ?
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

    /**
     * @param int|null $instanceId exemplaire individualisé mis en jeu.
     *        La ligne porte alors une RÉFÉRENCE : usure et nom restent
     *        sur item_instances. Un exemplaire est unique, la quantité
     *        est forcée à 1 — l'accepter ouvrirait une duplication.
     */
    public function add_item_to_exchange($itemId, $n, $itemOwnerId, ?int $instanceId = null){
        $this->refuse_exchange(true,true);

        $values = array(
            'exchange_id'=>$this->id,
            'item_id'=>$itemId,
            'n'=>$instanceId !== null ? 1 : $n,
            'player_id'=>$itemOwnerId == $this->playerId ? $this->playerId : $this->targetId,
            'target_id'=>$itemOwnerId == $this->playerId ? $this->targetId : $this->playerId
        );

        if($instanceId !== null){
            $values['instance_id'] = $instanceId;
        }

        $this->db->insert('players_items_exchanges', $values);
    }

    /**
     * Retire UNE ligne, visée par sa clé primaire.
     *
     * Elle ciblait ses lignes par (exchange_id, item_id, n, player_id) —
     * faute de clé primaire sur la table — et supprimait donc toutes les
     * lignes identiques d'un coup. Deux exemplaires du même objet ne
     * pouvaient plus se retirer séparément.
     */
    public function remove_item_line(int $lineId){
        $this->refuse_exchange(true,true);

        if($this->db->exe(
            'DELETE FROM players_items_exchanges WHERE id = ? AND exchange_id = ?',
            array($lineId, $this->id),
            true
        )==false){
            throw new Exception('Erreur lors de la suppression de l\'objet de l\'échange');
        }
    }

    /**
     * Purge des lignes après règlement — acceptation comme annulation.
     *
     * Elle n'existait NULLE PART : les lignes survivaient à l'échange.
     * Anodin sur des piles (les objets sont livrés, la ligne devient un
     * vestige) ; faux dès qu'un exemplaire est séquestré, puisque sa
     * ligne d'échange est alors la seule preuve que sa localisation
     * « exchange » est légitime. Sans cette purge, un exemplaire livré
     * resterait rattaché à un échange clos.
     */
    public function purge_items(){
        $this->db->exe(
            'DELETE FROM players_items_exchanges WHERE exchange_id = ?',
            array($this->id)
        );
        $this->items = [];
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
        $result ="";
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
            $item->get_data();

            /* Exemplaire : il change de PROPRIÉTAIRE et retombe en
             * banque chez le destinataire. Transfert conditionnel — si
             * l'exemplaire n'est plus séquestré chez le cédant (échange
             * réglé deux fois, objet détruit entre-temps), il lève au
             * lieu d'être livré une seconde fois. */
            if(!empty($exchange_item->instance_id)){

                $instanceService = new \App\Service\ItemInstanceService();
                $label = $instanceService->describe((int) $exchange_item->instance_id);

                $instanceService->deliverEscrow(
                    (int) $exchange_item->instance_id,
                    (int) $from_player->id,
                    (int) $to_player->id,
                    \App\Service\ItemInstanceService::LOCATION_EXCHANGE
                );

                if(!empty($result))
                    $result.=", ";
                $result.=$label;

                continue;
            }

            $item->add_item($to_player, $exchange_item->n, true);
            if(!empty($result))
                $result.=", ";
            $result.=$exchange_item->n." ".$item->data->name;
        }

        if(empty($result)){
            $result = 'Rien';
        }
        return $result;
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

            /* Les deux parties doivent voir CE qu'elles échangent : un
             * exemplaire porte son nom et son usure, pas une quantité. */
            if(!empty($exchange_item->instance_id)){

                $return .= '<li>'
                    . \App\Service\ItemInstanceService::label(
                        $exchange_item->custom_name,
                        (string) $item->data->name
                    )
                    . ' <small>'
                    . \App\Service\ItemInstanceService::stateLine($exchange_item, withBreak: false)
                    . '</small></li>';
                $noItem = false;

                continue;
            }

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

    public static function get_all_open_exchanges(){

        $return = array();

        $sql = 'SELECT * FROM items_exchanges WHERE (target_ok = 0 or player_ok = 0)';


        $db = new Db();
        $res = $db->exe($sql);

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