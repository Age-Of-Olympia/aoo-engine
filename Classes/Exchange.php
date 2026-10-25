<?php
namespace Classes;

use Exception;

class Exchange{

    public Db $db;

    public int $id;
    public int $playerId;
    public int $targetId;
    public string $exchangeType = 'merchant';
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
        SELECT player_id, target_id, player_ok, target_ok, update_time, exchange_type
        FROM items_exchanges
        where id = ?
        ';



        $res = $this->db->exe($sql, array($this->id));

        while($row = $res->fetch_object()){
            $this->playerId = $row->player_id;
            $this->targetId = $row->target_id;
            $this->exchangeType = $row->exchange_type;
            $this->playerOk = $row->player_ok;
            $this->targetOk = $row->target_ok;
            $this->updateTime = $row->update_time;
        }

    }

    public function get_items_data() {
        $this->items = [];
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
                   ' . \App\Service\ItemInstanceService::WEAR_SELECT . ',
                   i.quality, i.custom_name, i.destroyed,
                   cat.name, COALESCE(NULLIF(cat.label, \'\'), cat.name) AS label
            FROM players_items_exchanges e
            JOIN items cat ON cat.id = e.item_id
            LEFT JOIN item_instances i ON i.id = e.instance_id
            LEFT JOIN items it ON it.id = i.item_id
            LEFT JOIN players_bonus wear ON wear.player_id = i.entity_id AND wear.name = "pv"
            WHERE e.exchange_id = ?
        ';


        $res = $this->db->exe($sql, array($this->id));

        while($row = $res->fetch_object()){
            $this->items[] = $row;
        }
    }

    public function create($playerId,$targetId, string $exchangeType = 'merchant'){

        $this->playerId = $playerId;
        $this->targetId = $targetId;

        $values = array(
            'player_id'=>$playerId,
            'target_id'=>$targetId,
            'exchange_type'=>$exchangeType,
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
    
    /**
     * Locks the exchange row until the transaction ends and reloads flags and
     * items from it: two concurrent settlements (a double click) cannot both
     * deliver. The later one finds the exchange closed and throws.
     */
    public function lockInProgress(): void
    {
        $row = $this->db->exe(
            'SELECT player_ok, target_ok, update_time FROM items_exchanges WHERE id = ? FOR UPDATE',
            array($this->id)
        )->fetch_object();
        if (!$row || ((int) $row->player_ok !== 0 && (int) $row->target_ok !== 0)) {
            throw new \InvalidArgumentException('Cet échange n’est plus disponible.');
        }
        $this->playerOk = (int) $row->player_ok;
        $this->targetOk = (int) $row->target_ok;
        $this->updateTime = $row->update_time;
        $this->items = [];
        $this->get_items_data();
    }

    /** Throws when the deal changed since it was loaded: what is agreed is what was shown. */
    public function accept_exchange($Istarget){
        $shown = $this->updateTime;
        $this->lockInProgress();
        if ((int) $shown !== (int) $this->updateTime) {
            throw new \InvalidArgumentException('L’échange a été modifié. Vérifiez les objets avant de valider.');
        }

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

    /** Unconditional: in-memory flags may predate the other party's accept. */
    public function refuse_exchange($Istarget,$IsPlayer){
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

    /** @param bool $toBag the receiver is at the counter (a cancel): items go to the bag, not the bank */
    public function give_items( $from_player, $to_player, bool $toBag = false ){
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

            /* Exemplaire : il change de PROPRIÉTAIRE et arrive en
             * banque chez le destinataire (au sac s'il est au comptoir). Transfert conditionnel — si
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
                    \App\Service\ItemInstanceService::LOCATION_EXCHANGE,
                    $toBag ? \App\Service\ItemInstanceService::LOCATION_INVENTORY : \App\Service\ItemInstanceService::LOCATION_BANK
                );

                if(!empty($result))
                    $result.=", ";
                $result.=$label;

                continue;
            }

            $item->add_item($to_player, $exchange_item->n, !$toBag);
            if(!empty($result))
                $result.=", ";
            $result.=$exchange_item->n." ".$item->data->name;
        }

        if(empty($result)){
            $result = 'Rien';
        }
        return $result;
    }

    /**
     * What one party puts in, shaped like a pane of ExchangePanesView.
     *
     * @return array{stacks: list<array<string, mixed>>, exemplars: list<array<string, mixed>>}
     */
    public function contentsOf(int $playerId): array
    {
        $contents = ['stacks' => [], 'exemplars' => []];
        foreach ($this->items as $row) {
            if ((int) $row->player_id !== $playerId) {
                continue;
            }
            $entry = (array) $row;
            if (!empty($row->instance_id)) {
                // Both parties see the wear of what they trade
                $entry['state'] = \App\Service\ItemInstanceService::stateLine($row, withBreak: false);
                $contents['exemplars'][] = $entry;
                continue;
            }
            $contents['stacks'][] = $entry;
        }

        return $contents;
    }

    /** Bag -> exchange. A pile joins the line the owner already has for that item. */
    public function give(Player $owner, int $n, ?int $itemId = null, ?int $instanceId = null): void
    {
        if ($instanceId !== null) {
            $itemId = (int) $this->db->exe('SELECT item_id FROM item_instances WHERE id = ?', $instanceId)->fetch_object()?->item_id;
            (new \App\Service\ItemInstanceService())->escrowForExchange($instanceId, (int) $owner->id);
            $this->add_item_to_exchange($itemId, 1, $owner->id, $instanceId);
            return;
        }

        if ($n < 1 || !(new Item($itemId))->add_item($owner, -$n)) {
            throw new \InvalidArgumentException('Vous n\'avez pas cette quantité dans votre sac.');
        }
        $line = $this->ownLine((int) $owner->id, $itemId, null);
        if ($line !== null) {
            $this->remove_item_line((int) $line->id);
            $n += (int) $line->n;
        }
        $this->add_item_to_exchange($itemId, $n, $owner->id);
    }

    /** Exchange -> bag, refused when the bag is full. Taking part of a pile leaves the rest in the exchange. */
    public function takeBack(Player $owner, int $n, ?int $itemId = null, ?int $instanceId = null): void
    {
        $line = $this->ownLine((int) $owner->id, $itemId, $instanceId);
        if ($line === null || ($instanceId === null && ($n < 1 || $n > (int) $line->n))) {
            throw new \InvalidArgumentException('Cet objet n\'est pas dans l\'échange.');
        }

        if ($instanceId !== null) {
            (new \App\Service\ItemInstanceService())->releaseFromExchange($instanceId, (int) $owner->id);
            $this->remove_item_line((int) $line->id);
            return;
        }

        $bag = new \App\Service\ContainerService();
        if ($bag->stackNeedsRoom((int) $owner->id, $itemId) && !$bag->hasRoomForALine((int) $owner->id)) {
            throw new \InvalidArgumentException('Votre sac est plein.');
        }

        $this->remove_item_line((int) $line->id);
        if ((int) $line->n > $n) {
            $this->add_item_to_exchange($itemId, (int) $line->n - $n, $owner->id);
        }
        (new Item($itemId))->add_item($owner, $n);
    }

    /**
     * Cancel by a player at the counter: their own items come back to their bag
     * (refused when it cannot hold them all), the other party's go to their bank.
     */
    public function cancelReturns(Player $canceller, Player $other): void
    {
        $this->lockInProgress();
        $bag = new \App\Service\ContainerService();
        $own = $this->contentsOf((int) $canceller->id);
        $lines = count($own['exemplars']);
        foreach ($own['stacks'] as $row) {
            $lines += (int) $bag->stackNeedsRoom((int) $canceller->id, (int) $row['item_id']);
        }
        $capacity = $bag->capacityOf((int) $canceller->id);
        if ($capacity !== null && $lines > 0 && $bag->lineCountOf((int) $canceller->id) + $lines > $capacity) {
            throw new \InvalidArgumentException('Votre sac est plein.');
        }

        $this->give_items($canceller, $canceller, toBag: true);
        $this->give_items($other, $other);
    }

    /** The owner's line for a pile ($instanceId null) or for one exemplar. */
    private function ownLine(int $ownerId, ?int $itemId, ?int $instanceId): ?object
    {
        foreach ($this->items as $row) {
            if ((int) $row->player_id !== $ownerId) {
                continue;
            }
            if ($instanceId !== null ? (int) $row->instance_id === $instanceId : (empty($row->instance_id) && (int) $row->item_id === $itemId)) {
                return $row;
            }
        }

        return null;
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
