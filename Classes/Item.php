<?php
namespace Classes;

use Exception;

class Item{

    /**
     * Types d'objets à comportement câblé (items.type) — source unique
     * des littéraux, revue DRY 2026-07-18 :
     * - TYPE_CONSTRUCTIBLE : se construit DEPUIS L'INVENTAIRE en vraie
     *   entité bâtiment (action générique construire, choix de case).
     */
    public const TYPE_CONSTRUCTIBLE = 'constructible';

    /**
     * Groupes de colonnes du catalogue items — sources uniques (revue
     * ménage n°2) : l'admin (formulaire + save), le seeder et les
     * bundles dérivent d'ICI, jamais de listes littérales.
     */
    public const SPECIAL_KEYS = [
        'esquive', 'pr', 'pf', 'malus', 'spellMalus', 'fixedF', 'mDamage',
        'demolition', 'craftedByN', 'lootChance',
    ];
    public const FLAG_KEYS = ['cursed', 'enchanted', 'vorpal', 'is_bankable', 'is_deprecated'];
    public const WEAR_TRIGGERS = ['attack', 'defense', 'move', 'usage'];
    public const JSON_COLUMNS = ['add_effects', 'forbid', 'extra'];

    public $id;
    public $row;
    public $data;

    function __construct($itemId, $row=false,$checked=false){


        $this->id = $itemId;

        if(!$row){

            $db = new Db();

            $res = $db->get_single('items', $this->id);

            $row = $res->fetch_object();

        }

        if($checked && $row==null)
        {
            ExitError("Item not found");
        }

        $this->row = $row;
    }


    public function get_data(){


        /* Passerelle JSON→DB (Version20260717180000) : quand les stats
         * sont en base (stats_in_db), l'objet data est reconstruit depuis
         * les colonnes — même forme éparse que les JSON historiques (les
         * clés à zéro/vides n'existent pas, applyItemCaracs et les
         * consommateurs testent en !empty/isset) ; le fourre-tout `extra`
         * est reservi verbatim. Sinon : chemin JSON hérité inchangé. */
        if(!empty($this->row->stats_in_db)){

            $itemJson = $this->buildDataFromRow();
        }
        else{

            $itemJson = json()->decode('items', $this->row->name);
        }


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


    /**
     * Reconstruit l'objet data depuis les colonnes de la table items
     * (stats_in_db = 1), au format éparse des JSON historiques : les
     * scalaires vides/à zéro ne sont pas émis, les colonnes JSON
     * (munitions, add_effects, forbid) sont décodées, `extra` est
     * fusionné verbatim (garantie sans-perte du seed).
     */
    private function buildDataFromRow(): object {

        $data = (object) array(
            'id' => (int) $this->row->id,
            'name' => $this->row->name,
            'private' => (int) $this->row->private,
            'price' => (int) $this->row->price,
            'text' => (string) ($this->row->text ?? ''),
        );

        foreach (\App\Service\ItemStatsSeeder::SCALAR_KEYS as $key) {
            if ($key === 'text' || $key === 'price') {
                continue;
            }
            $value = $this->row->$key ?? null;
            if ($value === null || $value === '' || (is_numeric($value) && (int) $value === 0)) {
                continue;
            }
            $data->$key = is_numeric($value) ? (int) $value : $value;
        }

        foreach (['munitions' => 'munitions', 'add_effects' => 'addEffects', 'forbid' => 'forbid'] as $column => $key) {
            if (!empty($this->row->$column)) {
                $decoded = json_decode((string) $this->row->$column);
                if ($decoded !== null) {
                    $data->$key = $decoded;
                }
            }
        }

        if (!empty($this->row->extra)) {
            $extra = json_decode((string) $this->row->extra);
            if (is_object($extra)) {
                foreach (get_object_vars($extra) as $key => $value) {
                    $data->$key = $value;
                }
            }
        }

        return $data;
    }


    public function add_item($player, int $n, bool $bank=false):bool {
        if(!is_numeric($n) || $n == 0){
            exit('error n '. $n);
        }

        $bankSuffix = ($bank === true) ? '_bank' : '';

        if (is_numeric($player)) {
            $player = new Player($player);
        }

        if ($n < 0) {
            // Garde STRICTEMENT sur la pile : add_item ne sait décrémenter
            // que des piles, compter les instances ouvrirait une duplication.
            $available = $this->get_n($player, $bank, includeInstances: false);
            if ($available + $n < 0) {
                return false;
            }
        }

        $db = new Db();
        $db->start_transaction('add_item');

        $sql = '
        INSERT INTO players_items' . $bankSuffix . '
        (player_id, item_id, n)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
        n = n + VALUES(n);
        ';

        $db->exe($sql, array($player->id, $this->id, $n));

        if ($n < 0) {
            $sql = '
            DELETE FROM players_items' . $bankSuffix . '
            WHERE player_id = ? AND n <= 0
            ';
            $db->exe($sql, $player->id);
        }

        $db->commit_transaction('add_item');

        if(!$bank)
            $player->refresh_invent();

        return true;
    }


    /**
     * @param bool $includeInstances compter aussi les objets
     *        individualisés (défaut). Les MUTATEURS de piles (add_item,
     *        coûts consommés) doivent passer false : leur garde porte sur
     *        ce qu'ils peuvent réellement décrémenter — compter les
     *        instances y ouvrirait une duplication (retirer une unité de
     *        pile inexistante pendant que l'instance survit).
     */
    public function get_n($player, bool $bank=false, bool $equiped=false, bool $includeInstances=true): int {
        if (!isset($this->row) || !isset($this->row->name)) {
            return 0;
        }

        $bankSuffix = ($bank === true) ? '_bank' : '';

        if ($bank && $equiped) {
            return 0; // Cannot check equipped items in bank
        }

        $equipedCondition = $equiped ? 'AND equiped != ""' : '';

        $playerId = is_numeric($player) ? $player : (isset($player->id) ? $player->id : 0);
        if (!$playerId) {
            return 0;
        }

        $sql = '
        SELECT n, name
        FROM players_items' . $bankSuffix . '
        INNER JOIN items ON item_id = items.id
        WHERE player_id = ? AND name = ? ' . $equipedCondition . '
        ';

        $db = new Db();
        $res = $db->exe($sql, array($playerId, $this->row->name));

        $stackN = 0;
        if ($res && $res->num_rows) {
            $stackN = (int) $res->fetch_object()->n;
        }

        // Double comptage : les instances vivantes comptent
        // avec les piles (contrat pinné par ItemInstanceService::countOwned).
        if (!$bank && $includeInstances) {
            $stackN += (new \App\Service\ItemInstanceService())
                ->countInstances((int) $playerId, (int) $this->id, $equiped);
        }

        return $stackN;
    }


    public function give_item(Player $player, Player $target, int $n, bool $bank=false) {
        if ($n < 1) {
            return false;
        }
        if (!is_object($player) || !is_object($target)) {
            return false;
        }

        $db = new Db();
        $db->start_transaction('give_item');

        if (!$this->add_item($player, -$n, $bank)) {
            $db->rollback_transaction('give_item');
            return false;
        }

        if (!$this->add_item($target, $n, $bank)) {
            $db->rollback_transaction('give_item');
            return false;
        }

        $db->commit_transaction('give_item');
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

    public function get_recipe(bool $deprecated=false) : array{


        $craftJson = json()->decode('', $deprecated? 'oldcrafts': 'crafts');

        if(!$craftJson){

           throw new Exception(($deprecated? 'oldcrafts': 'crafts').' json not found');
        }
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


    public function is_bankable(){
        return $this->row->is_bankable;
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


    public static function get_item_by_name($name, $checked=false){

        $db = new Db();

        $sql = 'SELECT * FROM items WHERE name = ?';

        $res = $db->exe($sql, $name);

        if(!$res->num_rows){
            if($checked)
            {
                ExitError("Item by name not found");
            }
            return false;
        }

        $row = $res->fetch_object();

        return new Item($row->id,$row, $checked);
    }


    public static function get_equiped_list($player) : array {

        return self::get_item_list($player, bank:false, equiped:true);
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


        /* Double lecture (docs/design-items-instances.md §5c P2) — double
         * lecture : les objets individualisés (item_instances) rejoignent
         * la liste, façonnés comme des lignes de pile (n=1) + méta
         * d'instance. Clés : par id catalogue quand on liste l'ÉQUIPÉ
         * (contrat des emplacements/caracs — au plus un par emplacement),
         * par 'i{instanceId}' sinon (chaque individu = sa propre ligne). */
        if(!$bank){

            $instances = (new \App\Service\ItemInstanceService())
                ->listForInventory($playerId, $equiped !== '');

            foreach($instances as $instanceRow){

                $row = (object) $instanceRow;
                $key = ($equiped !== '') ? $row->id : 'i'. $row->instance_id;
                $return[$key] = $row;
            }
        }

        // or
        if(!isset($return[1]) && !$equiped){

            $return[1] = (object) array('id'=>1,'name'=>'or','price'=>1,'n'=>0, 'equiped'=>'', 'is_bankable'=>1);

            foreach(ITEMS_OPT as $k=>$e){

                $return[1]->$k = 0;
            }
        }

        /* Tri final : les ÉQUIPÉS d'abord (piles héritées comme
         * instances — le SQL ne couvre que les piles), puis par nom.
         * uasort préserve les clés (contrat des consommateurs). */
        uasort($return, static function ($a, $b): int {
            $aEquiped = !empty($a->equiped);
            $bEquiped = !empty($b->equiped);
            if ($aEquiped !== $bEquiped) {
                return $aEquiped ? -1 : 1;
            }
            return strcasecmp((string) ($a->name ?? ''), (string) ($b->name ?? ''));
        });

        return $return;
    }


    public static function get_formatted_name($name, $row){

        foreach(ITEMS_OPT as $k=>$e){
            if(!empty($row->$k)){ $name = $e . $name . $e; }
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


        // objet à sort intégré (items.spell) — le sort s'affiche en bleu
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

        //special effets
        if(!empty($itemJson->effet)){
            foreach($itemJson->effet as $effet){
                if (str_starts_with($effet,"-")){
                    $return[] = '<font color="blue">'. $effet .'</font>';
                } else {
                    $return[] = '<font color="red">'. $effet .'</font>';
                }
            }
        }
        
        
        //special pf
        if(!empty($itemJson->pf)){

            // item have this bonus
            $carac = $itemJson->pf;

            // bonus blue or malus red
            if( $carac > 0 )
                $return[] = '<font color="blue">PF+'. $carac .'</font>';
            if( $carac < 0 )
                $return[] = '<font color="red">PF'. $carac .'</font>';
        }

        //special malus
        if(!empty($itemJson->malus)){

            // item have this bonus
            $carac = $itemJson->malus;

            // bonus blue or malus red
            if( $carac < 0 )
                $return[] = '<font color="blue">Malus'. $carac .'</font>';
            if( $carac > 0 )
                $return[] = '<font color="red">Malus+'. $carac .'</font>';
        }
        
        // special demolition
        if(!empty($itemJson->demolition)){

            $return[] = '<font color="blue">démolition+'. $itemJson->demolition .'</font>';
        }

         // special esquive
         if(!empty($itemJson->esquive)){
            $color = "red"; 
            if($itemJson->esquive > 0){
                $color = "blue";
            }
            $return[] = '<font color="' . $color . '">Esq'. $itemJson->esquive .'</font>';
        }
        
        // special effects
        if(!empty($itemJson->addEffects)){

            foreach($itemJson->addEffects as $e){

                $return[] = '<font color="blue">+'. $e->name .'</font>';
            }
        }

        // pr
        if(!empty($itemJson->pr)){
            $return[] = '<font color="blue">Pr+'. $itemJson->pr .'</font>';
        }

        return $return;
    }


    /** Emplacements that do NOT count toward the ITEM_LIMIT equip cap. */
    public const EQUIP_LIMIT_EXEMPT = array('trophee', 'munition', 'doigt');

    /**
     * Whether an emplacement counts toward the ITEM_LIMIT cap. A ring (doigt),
     * a munition and a trophee are worn on top of the limit. Shared so the
     * simulator applies the same equip rule as the game.
     */
    public static function countsTowardEquipLimit(string $emplacement): bool
    {
        return !in_array($emplacement, self::EQUIP_LIMIT_EXEMPT, true);
    }

    public static function get_free_emplacement($player) : int{


        $values = ITEM_EMPLACEMENT_FORMAT;


        foreach($values as $k=>$e){

            if(!self::countsTowardEquipLimit($e)){

                unset($values[$k]);
            }
        }


        // count emplacements — piles héritées ET instances
        $sql = '
        SELECT
        (SELECT COUNT(*) FROM players_items
         WHERE player_id = ? AND equiped IN('. Db::print_in($values) .'))
        +
        (SELECT COUNT(*) FROM players_items_instances
         WHERE player_id = ? AND equiped IN('. Db::print_in($values) .'))
        AS n
        ';

        $params = array_merge(array($player->id), $values, array($player->id), $values);

        $db = new Db();

        $res = $db->exe($sql, $params);

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
