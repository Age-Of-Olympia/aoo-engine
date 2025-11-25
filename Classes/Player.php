<?php
namespace Classes;

use App\Enum\EquipResult;
use App\Interface\ActorInterface;
use App\Service\ActionService;
use App\Service\PlayerService;
use Exception;
use Throwable;

class Player implements ActorInterface {

    public $id;
    public object $data;
    public $caracs;
    public $upgrades;
    public $coords;
    public $nude;
    public $raceData;
    public $debuffs;
    public $turn;
    public $emplacements;
    public $row;
    public $playerService;
    function __construct($playerId){

        $this->id = $playerId;

        $this->caracs = (object) array();
        $this->upgrades = (object) array();
        $this->emplacements = (object) array();
        $this->playerService = new PlayerService($playerId);
    }

    public function getId(): int {
        return $this->id;
    }

    /**
     * Check if this is a real player (not tutorial, not NPC)
     * Uses player_type discriminator column
     */
    public function isRealPlayer(): bool {
        // NPCs always have negative IDs
        if ($this->id < 0) {
            return false;
        }

        // Load player data if not already loaded
        if (!isset($this->data)) {
            $this->get_data();
        }

        // Check player_type discriminator (defaults to 'real' if not set)
        return ($this->data->player_type ?? 'real') === 'real';
    }

    /**
     * Check if this is a tutorial player (temporary character)
     */
    public function isTutorialPlayer(): bool {
        // Tutorial players have positive IDs but player_type='tutorial'
        if ($this->id < 0) {
            return false;
        }

        if (!isset($this->data)) {
            $this->get_data();
        }

        return ($this->data->player_type ?? 'real') === 'tutorial';
    }

    /**
     * Check if this is an NPC (non-player character)
     */
    public function isNPC(): bool {
        // NPCs traditionally have negative IDs
        if ($this->id < 0) {
            return true;
        }

        if (!isset($this->data)) {
            $this->get_data();
        }

        return ($this->data->player_type ?? 'real') === 'npc';
    }

    /**
     * Check if this player should appear in public lists (rankings, leaderboards)
     * Only real players appear in public lists
     */
    public function isPubliclyVisible(): bool {
        return $this->isRealPlayer();
    }

    /**
     * Get player type ('real', 'tutorial', 'npc')
     */
    public function getPlayerType(): string {
        if ($this->id < 0) {
            return 'npc';
        }

        if (!isset($this->data)) {
            $this->get_data();
        }

        return $this->data->player_type ?? 'real';
    }


    public function get_row(){


        $db = new Db();

        $res = $db->get_single('players', $this->id);


        if(!$res->num_rows){

            exit('error player id:'.strval($this->id));
        }


        $row = $res->fetch_object();


        $row->text = htmlentities($row->text);
        $row->story = htmlentities($row->story);


        $this->row = $row;
    }


    public function get_caracs(bool $nude=false): bool {


        if(!isset($this->data)){

            $this->get_data();
        }


        $raceJson = json()->decode('races', $this->data->race);

        $this->raceData = $raceJson;

        // Initialize caracs object if not exists
        if (!isset($this->caracs) || !is_object($this->caracs)) {
            $this->caracs = new \stdClass();
        }

        // Initialize raceData if decode failed
        if (!$this->raceData || !is_object($this->raceData)) {
            error_log("[Player] WARNING: Race data not found for race '{$this->data->race}' (player {$this->id}). Using defaults.");
            $this->raceData = new \stdClass();
            // Initialize default race stats to 0
            foreach(CARACS as $k=>$e){
                $this->raceData->$k = 0;
            }
        }

        $this->get_upgrades();

        // Double-check all objects are initialized (defensive programming)
        if (!is_object($this->caracs)) $this->caracs = new \stdClass();
        if (!is_object($this->raceData)) $this->raceData = new \stdClass();
        if (!is_object($this->upgrades)) $this->upgrades = new \stdClass();

        foreach(CARACS as $k=>$e){
            // Ensure properties exist before adding
            $raceValue = isset($this->raceData->$k) ? $this->raceData->$k : 0;
            $upgradeValue = isset($this->upgrades->$k) ? $this->upgrades->$k : 0;

            $this->caracs->$k = $raceValue + $upgradeValue;
        }


        if($nude){

            return false;
        }


        $this->nude = clone $this->caracs;


        $itemList = Item::get_equiped_list($this);

        foreach($itemList as $row){


            $item = new Item($row->id, $row);

            $item->get_data();


            $this->emplacements->{$row->equiped} = $item;


            foreach(CARACS as $k=>$e){


                if(!empty($item->data->$k)){


                    $this->caracs->$k += $item->data->$k;
                }
            }


            // fixed caracs
            if(!empty($item->data->fixedF)){


                $this->caracs->f = $item->data->fixedF;
            }
        }

        // Esquive
        if(!empty($item->data->esquive)){

            $this->caracs->esquive = $item->data->esquive;
        }

        // elements de debuffs
        $effectsList = $this->get_effects();

        $this->debuffs = (object) array();


        foreach($effectsList as $e){


            if(!empty(ELE_DEBUFFS[$e])){


                $this->caracs->{ELE_DEBUFFS[$e]} -= 1;

                $this->debuffs->{ELE_DEBUFFS[$e]} = $e;
            }
        }


        // turn caracs with bonus / malus
        $sql = '
        SELECT name, n FROM
        players_bonus
        WHERE
        player_id = ?
        ';

        $db = new Db();

        $res = $db->exe($sql, $this->id);

        $this->turn = (object) array();

        while($row = $res->fetch_object()){
            $this->turn->{$row->name} = $this->caracs->{$row->name} + $row->n;
        }


        // save .turn
        $data = Json::encode($this->turn);
        Json::write_json('datas/private/players/'. $this->id .'.turn.json', $data);


        // fist
        if(!isset($this->emplacements->main1)){


            $item = Item::get_item_by_name('poing');

            $item->get_data();

            
            $this->emplacements->main1 = $item;
        }


        // save .caracs
        $data = Json::encode($this->caracs);
        Json::write_json('datas/private/players/'. $this->id .'.caracs.json', $data);
        return true;
    }


    public function get_caracsJson(){


        if(!$caracsJson = json()->decode('players', $this->id .'.caracs')){

            $this->get_caracs();

            $caracsJson = json()->decode('players', $this->id .'.caracs');
        }

        return $caracsJson;
    }

    public function get_turnTurnJson(){


        if(!$turnJson = json()->decode('players', $this->id .'.turn')){

            $this->get_caracs();

            $turnJson = json()->decode('players', $this->id .'.turn');
        }

        return $turnJson;
    }


    public function get_turnJson(){


        if(!$turnJson = json()->decode('players', $this->id .'.turn')){

            $this->get_caracs();

            $turnJson = json()->decode('players', $this->id .'.turn');
        }

        return $turnJson;
    }


    public function get_upgrades(){

        // Initialize upgrades object if not exists
        if (!isset($this->upgrades) || !is_object($this->upgrades)) {
            $this->upgrades = new \stdClass();
        }

        foreach(CARACS as $k=>$e){

            $this->upgrades->$k = 0;
        }

        foreach($this->get('upgrades') as $e){

            $this->upgrades->$e += 1;
        }

        return $this->upgrades;
    }


    public function getCoords(bool $refresh = true): object{


        if (!$refresh && isset($this->coords)) {

            return $this->coords;
        }

        $this->get_data(false);
        $db = new Db();


        // first coords
        if ($this->data->coords_id == NULL) {


            $coords = (object) array(
                'x' => 0,
                'y' => 0,
                'z' => 0,
                'plan' => 'olympia'
            );

            // spawn player
            $this->move_player($coords);
        }

        $sql = '
            SELECT
            x, y, z, plan
            FROM
            coords AS c
            INNER JOIN
            players AS p
            ON
            p.coords_id = c.id
            WHERE
            p.id = ?
            ';

        $res = $db->exe($sql, $this->id);

        $row = $res->fetch_object();

        $coords = (object) array(
            'x' => $row->x,
            'y' => $row->y,
            'z' => $row->z,
            'plan' => $row->plan
        );

        $this->coords = $coords;


        return $this->coords;
    }


    public function move_player($coords){

        $this->go($coords);
    }


    // have/add/end/get main functions
    public function have($table, $name): int{


        if(!in_array($table, array('effects','options','actions'))){

            exit('error have table');
        }


        $sql = '
        SELECT COUNT(*) AS n
        FROM
        players_'. $table .'
        WHERE
        player_id = '. $this->id .'
        AND
        name = "'. $name .'"
        ';

        $db = new Db();

        $count = $db->get_count($sql);

        return $count;
    }


    public function add($table, $name, $charges=false){


        $db = new Db();

        $values = array(
            'player_id'=>$this->id,
            'name'=>$name
        );


        if(!empty($charges)){

            $values['charges'] = $charges;
        }


        if($table == 'actions'){

            if ($name != 'attaquer') {
                $actionService = new ActionService();
                $action = $actionService->getActionByName($name);
                if ($action != null) {
                    if ($action->getOrmType() == 'spell' || $action->getOrmType() == 'technique') {
                        $values['type'] = 'sort';
                    }
                }
            }
        }


        if($table == 'options'){


            if($name == 'isMerchant'){


                $this->add_follower('marchand', params:'on');
            }
        }


        $db->insert('players_'. $table, $values);
    }

    public function end($table, $name){

        $values = array(
            'player_id'=>$this->id,
            'name'=>$name
        );

        $db = new Db();

        $db->delete('players_'. $table, $values);


        if($name == 'isMerchant'){

            $this->delete_follower('marchand');
        }
    }

    public function get($table){


        $return = array();

        $db = new Db();

        $res = $db->get_single_player_id('players_'. $table, $this->id);

        while($row = $res->fetch_object()){

            $return[] = $row->name;
        }

        sort($return);

        return $return;
    }


    // options shortcuts
    public function add_option($name){ $this->add('options', $name); }
    public function have_option($name): int{ return $this->have('options', $name); }
    public function end_option($name){ $this->end('options', $name); }
    public function get_options(){ return $this->get('options'); }

    // actions shortcuts
    public function add_action($name, $charges=false){ $this->add('actions', $name, $charges); }
    public function have_action($name){ return $this->have('actions', $name); }
    public function end_action($name){ $this->end('actions', $name); }
    public function get_actions(){ return $this->get('actions'); }

    // spells shortcuts
    public function add_spell($name){ $this->add_action($name); }
    public function have_spell($name){ return $this->have_action($name); }
    public function end_spell($name){ $this->end_action($name); }
    public function get_spells(){


        $return = array();

        $sql = 'SELECT name FROM players_actions WHERE player_id = ? AND type = "sort"';

        $db = new Db();

        $res = $db->exe($sql, $this->id);

        while($row = $res->fetch_object()){

            $return[] = $row->name;
        }

        return $return;
    }


    // effects
    public function haveEffect(string $name): int{

        return $this->have('effects', $name);
    }

    public function addEffect($name, $duration=0): void{


        // effect exists
        if(!isset(EFFECTS_RA_FONT[$name])){

            exit('error effect name');
        }


        // duration (0 is unlimited)
        if($duration == 0){

            $endTime = 0;
        }

        else{

            $endTime = time() + $duration;
        }


        $sql = '
        INSERT INTO
        players_effects
        (player_id, name, endTime)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
        endTime = VALUES(endTime);
        ';

        $db = new Db();

        $db->exe($sql, array($this->id, $name, $endTime));


        // element control
        if(!empty(ELE_CONTROLS[$name])){

            if($this->haveEffect(ELE_CONTROLS[$name])){

                $this->endEffect(ELE_CONTROLS[$name]);

                // echo '<script>alert("'. ucfirst($name) .' annule '. ucfirst(ELE_CONTROLS[$name]) .'");document.location.reload();</script>';
            }

            if(!empty(ELE_IS_CONTROLED[$name])){


                if($this->haveEffect(ELE_IS_CONTROLED[$name])){

                    $this->endEffect(ELE_IS_CONTROLED[$name]);
                    $this->endEffect($name);

                    // echo '<script>alert("'. ucfirst(ELE_IS_CONTROLED[$name]) .' et '. ucfirst($name) .' s\'annulent!");document.location.reload();</script>';
                }
            }
        }
    }

    public function get_effects(){

        return $this->get('effects');
    }

    public function endEffect(string $name): void{


        $values = array(
            'player_id'=>$this->id,
            'name'=>$name
        );

        $db = new Db();

        $db->delete('players_effects', $values);
    }

    public function purge_effects(): int{


        $sql = '
        DELETE
        FROM
        players_effects
        WHERE
        player_id = ?
        AND
        endTime <=  '. time() .'
        AND
        endTime > 0
        ';

        $db = new Db();

        $affectedRows = $db->exe($sql, $this->id, false, true);
        return $affectedRows;
    }

    public function have_effects_to_purge(): bool{


        $sql = '
        SELECT COUNT(*) AS n
        FROM
        players_effects
        WHERE
        player_id = ?
        AND
        endTime <=  '. time() .'
        AND
        endTime > 0
        ';

        $db = new Db();

        $res = $db->exe($sql, $this->id);
        $row = $res->fetch_object();

        
        return $row->n > 0;
    }


    public function go($goCoords){


        // store older coords
        if(!isset($this->coords)){

            $this->getCoords();
        }

        $oldCoords = $this->coords;

        if (is_numeric($goCoords)) {
            $goCoords = View::get_coords_from_id($goCoords);
        }

        $coordsId = isset($goCoords->coordsId) ? $goCoords->coordsId : View::get_coords_id($goCoords);

        $zChange = ($oldCoords->z != $goCoords->z);


        $this->move_followers($coordsId);


        $sql = 'UPDATE players SET coords_id = ? WHERE id = ?';

        $db = new Db();
        $db->exe($sql, array($coordsId, $this->id));


        // territory change
        if($goCoords->plan != $oldCoords->plan){


            // update last travel time
            $sql = 'UPDATE players SET lastTravelTime = ? WHERE id = ?';

            $time = time();

            $db->exe($sql, array($time, $this->id));
        }


        $this->refresh_data();


        // add elements
        $sql = 'SELECT name, endTime FROM map_elements WHERE coords_id = ?';

        $res = $db->exe($sql, $coordsId);

        while($row = $res->fetch_object()){
            if(str_starts_with($row->name, 'trace_pas')){

              continue;
            }

            // fishing
            if($row->name == 'eau' && $row->endTime == 0){


                $item = Item::get_item_by_name('canne_a_peche');


                if(FISHING || ($item && $item->get_n($this, bank:false, equiped:true))){


                    $this->end_option('alreadyFished');

                    echo '
                    <script>
                        $(document).ready(function(){
                            if(!confirm("Ça mord!\nPêcher?")){

                                document.location.reload();
                                return false;
                            };
                            document.location = "fish.php";
                        });
                    </script>
                    ';
                }
            }


            $this->addEffect($row->name, ONE_DAY);
        }


        // void plan
        $planJson = json()->decode('plans', $this->coords->plan);

        if(!$planJson){
            $this->refresh_view();
        }
        else{
            View::refresh_players_svg($this->coords);
        }

        if ($goCoords->plan != $this->coords->plan || $zChange) {
            $goPlanJson = json()->decode('plans', $goCoords->plan);
            if ($goPlanJson) {
                View::refresh_players_svg($goCoords);
            }
        }

        $this->refresh_caracs();
        
        if (!$zChange) {
            $text = $this->data->name .' s\'est déplacé en '.$goCoords->x.','.$goCoords->y.','.$goCoords->z;
        } else {
            $text = $this->data->name .' a emprunté des escaliers. (Il est arrivé en '.$goCoords->x.','.$goCoords->y.','.$goCoords->z.')';
        }

        $this->coords = $goCoords;
        
        Log::put($this, $this, $text, "move");

        // Trigger automatic screenshot for movements on arene_s2
        if ($goCoords->plan === 'arene_s2' && $this->id >= 0) {
            try {
                $screenshotService = new \App\Service\ScreenshotService();
                $screenshotService->generateAutomaticScreenshot($this, 'move');
            } catch (Exception $e) {
                error_log("Error triggering automatic screenshot for movement: " . $e->getMessage());
            }
        }

       // delete empty coords will be cron managed for easier debugging
    }


    public function put_xp($xp){


        if(!isset($this->data)){

            $this->get_data();
        }

        // Ajout d'un cap temporaire des PIs pour la fin de la saison 1
        $xpCap = SEASON_XP;
        $pi = min(max(0,($xpCap - $this->data->xp)),$xp);
        $this->data->xp += $xp;
        $this->data->pi += $pi;

        // update rank
        $rank = Str::get_rank($this->data->xp);

        $sql = 'UPDATE players SET xp = xp + ?, pi = pi + ?, rank = ? WHERE id = ?';

        $db = new Db();

        $db->exe($sql, array($xp, $pi, $rank, $this->id));


        $this->refresh_data();
    }


    public function put_pr($pr){


        if(!isset($this->data)){

            $this->get_data();
        }

        if ($this->data->pr < $this->data->pr+$pr){

            for($n=$this->data->pr; $n<=$this->data->pr+$pr; $n++){
                
                if($n %50 == 0){

                    Forum::put_reward($this);
                }
            }
        }


        $sql = 'UPDATE players SET pr = pr + ? WHERE id = ?';

        $db = new Db();

        $db->exe($sql, array($pr, $this->id));


        $this->refresh_data();
    }


    public function put_kill($target, $xp, $assist=0, $is_inactive=0){


        $db = new Db();

        $values = array(
            'player_id'=>$this->id,
            'target_id'=>$target->id,
            'player_rank'=>$this->data->rank,
            'target_rank'=>$target->data->rank,
            'xp'=>$xp,
            'assist'=>$assist,
            'is_inactive'=>$is_inactive,
            'time'=>time(),
            'plan'=>$target->coords->plan
        );

        $res = $db->insert('players_kills', $values);
        if (!$res) {
            exit('Erreur lors de l\'jout du kill, contactez l\'équipe ! (forum, discord)');
        }

        $this->refresh_kills();
    }


    public function put_assist($target, $damages){


        self::clean_players_assists();


        $db = new Db();

        $values = array(
            'player_id'=>$this->id,
            'target_id'=>$target->id,
            'player_rank'=>$this->data->rank,
            'damages'=>$damages,
            'time'=>time()
        );

        $sql = '
        INSERT INTO
        players_assists
        (`player_id`,`target_id`,`player_rank`,`damages`,`time`)
        VALUE('. implode(',', $values) .')
        ON DUPLICATE KEY UPDATE
        damages = damages + VALUES(damages), time = VALUES(time);
        ';

        $db->exe($sql);
    }


    public function refresh_view(){
        $file = $_SERVER['DOCUMENT_ROOT'].'/datas/private/players/'. $this->id .'.svg';
        if (is_file($file)) {
            unlink($file); // Delete the file
        }
    }

    public function refresh_data(){
        $file = $_SERVER['DOCUMENT_ROOT'].'/datas/private/players/'. $this->id .'.json';
        if (is_file($file)) {
            unlink($file); // Delete the file
        }
    }

    public function refresh_invent(){
        $file = $_SERVER['DOCUMENT_ROOT'].'/datas/private/players/'. $this->id .'.invent.html';
        if(file_exists($file)){
            unlink($file);
        }
    }

    public function refresh_kills(){
        $file = 'datas/private/players/'. $this->id .'.kills.html';
        if(file_exists($file)){
            unlink($file);
        }
    }

    public function refresh_caracs(){

        $this->get_caracs();
    }


    public function put_pf($pf){


        $this->data->pf += $pf;

        $sql = 'UPDATE players SET pf = pf + ? WHERE id = ?';

        $db = new Db();

        $db->exe($sql, array($pf, $this->id));

        $this->refresh_data();
    }


    public function put_upgrade($upgradeName, $cost){

        $values = array(
            'player_id'=>$this->id,
            'name'=>$upgradeName,
            'cost'=>$cost
        );
        
        $db = new Db();
        
        $db->insert('players_upgrades', $values);
        
        
        if($upgradeName == 'p'){
        
            $this->refresh_view();
        }
        
        
        $this->refresh_caracs();
    }

    public function remove_upgrade($upgradeName, $n){
        
        $db = new Db();
        
        $db->start_transaction('remove_upgrade');

        try{

        
            $sql = '
            select sum(upgrades.cost) as total from (select cost from players_upgrades where player_id = ? and name = ? order by cost desc limit ?) as upgrades
            ';

            $res = $db->exe($sql, array($this->id, $upgradeName,$n));

            $row = $res->fetch_object();

            $total_pi_rembouser = $row->total;

            $sql = '
            UPDATE players
            SET
            pi = pi + ?
            WHERE
            id = ?
            ';

            $sql = $db->exe($sql, array($total_pi_rembouser, $this->id));


            $sql = 'delete from players_upgrades where player_id = ? and name = ? order by cost desc limit ?';


            $db->exe($sql, array($this->id, $upgradeName,$n));

            $db->commit_transaction('remove_upgrade');
    
        } catch (Throwable $th) {
            $db->rollback_transaction('remove_ugprade');
            ExitError('Erreur lors du retrait de l\'upgrade. ');
        }            

        if($upgradeName == 'p'){

            $this->refresh_view();
        }


        $this->refresh_caracs();
    }

    public function putBonus($bonus) : bool{


        if(!isset($this->data)){

            $this->get_data();
        }


        if(!count($bonus)){

            return false;
        }


        if(!isset($this->caracs) || !count((array) $this->caracs)){

            $this->get_caracs();
        }

        $values = array();


        $db = new Db();


        foreach($bonus as $carac=>$val){


            $values[] = '('. $this->id .', "'. $carac .'", '. $val .')';
            
            if($carac == 'pv'){

                if($val < 0){


                    $this->put_malus(MALUS_PER_DAMAGES);

                    // add blood on floor
                    Element::put('sang', $this->data->coords_id);
                }

                elseif($val > 0){


                    $pvLeft = $this->getRemaining('pv');

                    if($pvLeft + $val > $this->caracs->pv){

                        $val = $pvLeft;
                    }
                }
            }

            elseif($carac == 'pm' && $val > 0){


                $pmLeft = $this->getRemaining('pm');

                if($pmLeft + $val > $this->caracs->pm){

                    $val = $pmLeft;
                }
            }
        }

        $sql = '
        INSERT INTO
        players_bonus
        (`player_id`,`name`,`n`)
        VALUE '. implode(',', $values) .'
        ON DUPLICATE KEY UPDATE
        n = n + VALUES(n);
        ';

        $db->exe($sql);


        if(!isset($this->turn)){

            $this->turn = (object) array();
        }

        if(!isset($this->turn->$carac)){

            $this->turn->$carac = $this->caracs->$carac;
        }

        $this->turn->$carac += $val;


        $sql = '
        DELETE FROM
        players_bonus
        WHERE
        name IN ("pm", "pv")
        AND
        n >= 0
        ';

        $db->exe($sql);


        $this->refresh_caracs();


        return true;
    }


    public function getRemaining(string $trait): int{


        if(!isset($this->caracs) || !get_object_vars($this->caracs)){


            $this->get_caracs();
        }



        if(!isset($this->turn->$trait)){
            if ($trait == "energie") {
                return $this->data->energie;
            }

            return $this->caracs->$trait;
        }

        return $this->turn->$trait;
    }


    public function put_malus($malus): void {

        $sql = 'UPDATE players SET malus = GREATEST(malus + ?, 0) WHERE id = ?';

        $db = new Db();

        $db->exe($sql, array($malus, $this->id));

        $this->refresh_data();
    }

    public function putEnergie($energie): void{


        $sql = 'UPDATE players SET energie = GREATEST(energie + ?, 0) WHERE id = ?';

        $db = new Db();

        $db->exe($sql, array($energie, $this->id));

        $this->put_malus(1);
        $this->refresh_data();
    }


    public function change_god($god){


        $sql = 'UPDATE players SET godId = ?, pf = 0 WHERE id = ?';

        $db = new Db();

        $db->exe($sql, array($god->id, $this->id));

        $this->refresh_data();
    }


    public function get_gold($bank=false){
        $item = Item::get_item_by_name('or');
        return $item->get_n($this, $bank);
    }


    public function drop($item, $n){


        if(!isset($this->data)){

            $this->get_data();
        }

        $values = array(
            'item_id'=>$item->id,
            'coords_id'=>$this->data->coords_id,
            'n'=>$n
        );

        $db = new Db();

        $db->insert('map_items', $values);


        $item->add_item($this, -$n);
    }


    public function change_avatar($file){

        $dir = 'img/avatars/'. $this->data->race .'/';

        $url = str_replace('/', '', $file);
        $url = str_replace('..', '', $url);
        $url = $dir . $url;

        if(!file_exists($url)){

            exit('error url');
        }


        $sql = 'UPDATE players SET avatar = ? WHERE id = ?';

        $db = new Db();

        $db->exe($sql, array($url, $this->id));


        $this->refresh_data();
        $this->refresh_view();
    }


    public function add_quest($quest){


        $questJson = json()->decode('quests', $quest);


        if(!$questJson){

            exit('error quest');
        }


        $sql = 'UPDATE players SET quest = ? WHERE id = ?';

        $db = new Db();

        $db->exe($sql, array($quest, $this->id));


        $values = array(
            'player_id'=>$this->id,
            'quest'=>$quest
        );

        $db->insert('players_quests', $values);


        $this->refresh_data();
    }


    public function get_quest($quest){


        $questJson = json()->decode('quests', $quest);


        if(!$questJson){

            exit('error quest');
        }


        $db = new Db();

        $sql = 'SELECT * FROM players_quests WHERE player_id = ? AND quest = ?';

        $res = $db->exe($sql, array($this->id, $quest));

        if(!$res->num_rows){

            exit('error player quest');
        }

        $row = $res->fetch_object();

        return $row;
    }


    public function get_new_mails($all=false){


        $db = new Db();

        if($all){

            $sql = '
            SELECT player_id, COUNT(*) AS n
            FROM
            players_forum_missives
            WHERE
            (
                player_id IN(
                    SELECT pnj_id FROM players_pnjs WHERE player_id = ?
                )
                OR
                player_id = ?
            )
            AND
            viewed = 0
            GROUP BY player_id';

            $res = $db->exe($sql, array($_SESSION['mainPlayerId'], $_SESSION['mainPlayerId']));
        }
        else{


            $sql = 'SELECT player_id, COUNT(*) AS n FROM players_forum_missives WHERE player_id = ? AND viewed = 0 GROUP BY player_id';

            $res = $db->exe($sql, $this->id);
        }

        $result = array();

        while($row = $res->fetch_object()){

            $result[$row->player_id] = $row->n;
        }

        return  $result;
    }


    public function add_follower($name, $params){


        $db = new Db();

        $values = array(
            'coords_id'=>$this->data->coords_id,
            'name'=>'marchand'
                    );

        $db->insert('map_foregrounds', $values);

        $sql = 'SELECT id FROM map_foregrounds WHERE name = ? AND coords_id = ?';

        $res = $db->exe($sql, array($name, $this->data->coords_id));

        $row = $res->fetch_object();

        $values = array(
            'player_id'=>$this->id,
            'foreground_id'=>$row->id,
            'params'=>$params
                    );

        $db->insert('players_followers', $values);
    }

    public function delete_follower($name){


        $db = new Db();

        $sql = '
        SELECT
        f.id AS followerId,
        foreground_id
        FROM
        players_followers AS f
        INNER JOIN
        map_foregrounds AS m
        ON
        f.foreground_id = m.id
        WHERE
        m.name = ?
        AND
        f.player_id = ?';

        $res = $db->exe($sql, array($name, $this->id));

        if($res->num_rows){


            $row = $res->fetch_object();


            $values = array('player_id'=>$this->id, 'foreground_id'=>$row->foreground_id);

            $db->delete('players_followers', $values);


            $values = array(
                'id'=>$row->foreground_id
                      );

            $db->delete('map_foregrounds', $values);
        }
    }


    public function move_followers($coordsId){


        $db = new Db();

        $res = $db->get_single_player_id('players_followers', $this->id);

        if($res->num_rows){


            while($row = $res->fetch_object()){


                $foreground_id = $row->foreground_id;

                $position = $row->params;

                if($position == 'last'){


                    $sql = '
                    UPDATE
                    map_foregrounds
                    SET
                    coords_id = ?
                    WHERE
                    id = ?
                    ';

                    $db->exe($sql, array($this->data->coords_id, $foreground_id));
                }

                elseif($position == 'on'){


                    $sql = '
                    UPDATE
                    map_foregrounds
                    SET
                    coords_id = ?
                    WHERE
                    id = ?
                    ';

                    $db->exe($sql, array($coordsId, $foreground_id));
                }
            }
        }
    }

    


    public function equip(Item $item, bool $doNotRefresh = false): EquipResult{

        $db = new Db();


        if(!isset($item->data)){

            $item->get_data();
        }


        if($item->row->name == 'poing'){

            return EquipResult::DoNothing;
        }


        $itemList = Item::get_equiped_list($this);


        if(!empty($itemList[$item->id])){


            // item is cursed
            if($item->row->cursed){

                echo '<div id="data">Objet Maudit !</div>';
                return EquipResult::Cursed;
            }

            // item is equiped : UNEQUIP

            $sql = '
            UPDATE
            players_items
            SET
            equiped = ""
            WHERE
            player_id = ?
            AND
            item_id = ?
            ';

            $db->exe($sql, array(
                $this->id,
                $item->id
            ));


            // refresh view when P change
            if(isset($item->data->p)){

                $this->refresh_view();
            }

            $return = EquipResult::Unequip;
        }


        else{
            
            // item is exo from another race
            if(!empty($item->row->exotique)){
                if($item->row->exotique != $this->data->race){
                    echo '<div id="data">Objet exotique d\'une autre race, impossible à équiper !</div>';
                    return EquipResult::DoNothing;
                }
            }

            // item is NOT equiped : EQUIP

            if(!empty($this->emplacements->{$item->data->emplacement}) && $this->emplacements->{$item->data->emplacement}->id == $item->id){
                return EquipResult::DoNothing;
            }


            if(!Item::get_free_emplacement($this)){
                if($item->data->emplacement != 'munition' && $item->data->emplacement != 'doigt'){
                    return EquipResult::NoRoom;
                }
            }


            // cursed emp
            $sql = '
            SELECT COUNT(*) AS n
            FROM items AS i
            INNER JOIN players_items AS p
            ON i.id = p.item_id
            WHERE p.player_id = ?
            AND p.equiped = ?
            AND i.cursed = 1
            ';

            $res = $db->exe($sql, array($this->id, $item->data->emplacement));

            $row = $res->fetch_object();

            if($row->n){

                echo '<div id="data">Objet Maudit!</div>';
                return EquipResult::Cursed;
            }


            // unequip emplacement
            $sql = '
            UPDATE
            players_items
            SET
            equiped = ""
            WHERE
            player_id = ?
            AND
            equiped = ?
            ';

            $db->exe($sql, array(
                $this->id,
                $item->data->emplacement,
            ));
            
            // unequip main1 and main2 if item is 2mains
            if($item->data->emplacement == "deuxmains"){
                $sql = '
                UPDATE
                players_items
                SET
                equiped = ""
                WHERE
                player_id = ?
                AND
                (equiped = "main1" OR equiped="main2")
                ';

                $db->exe($sql, array($this->id));
                }
            
            // unequip 2mains if item is main1 or main2
            elseif($item->data->emplacement == "main1" || $item->data->emplacement == "main2"){
                $sql = '
                UPDATE
                players_items
                SET
                equiped = ""
                WHERE
                player_id = ?
                AND
                equiped = "deuxmains"
                ';

                $db->exe($sql, array($this->id));
                }
            
            $sql = '
            UPDATE
            players_items
            SET
            equiped = ?
            WHERE
            player_id = ?
            AND
            item_id = ?
            ';

            $db->exe($sql, array(
                $item->data->emplacement,
                $this->id,
                $item->id
            ));
            

            // equip munitions
            if($munition = $this->getMunition($item)){

                if(!isset($itemList[$munition->id])){

                    $this->equip($munition);
                }
            }

            $return = EquipResult::Equip;
        }


        // in actions.php, refreshing will interact with "ignore equipement" script
        if(!$doNotRefresh){


            // in both case, refresh
            $this->refresh_invent();
            $this->refresh_caracs();
            $this->refresh_view();
        }

        return $return;
    }

    public function get_max_spells() : int{
        if(!isset($this->data)){
            $this->get_data();
        }
        $maxSpells = $this->data->rank + 1;

        if($this->data->race == 'hs'){
            $maxSpells += 1;
        }

        return $maxSpells;
    }

    public function get_spells_available($spellsN){
        return $this->get_max_spells() - $spellsN;
    }


    public function getMunition(Item $object, bool $equiped=false): ?Item {


        if(!isset($object->data->munitions)){

            return null;

        }

        foreach($object->data->munitions as $e){


            $munition = Item::get_item_by_name($e);

            if($munition->get_n($this, bank:false, equiped:$equiped) > 0){


                return $munition;
            }
        }

        return null;
    }


    public function death(){


        // drop loot
        $sql = '
        SELECT
        item_id, n, equiped,
        i.name
        FROM
        players_items AS pi
        INNER JOIN
        items AS i
        ON
        pi.item_id = i.id
        WHERE
        player_id = ?
        ';

        $db = new Db();

        $res = $db->exe($sql, $this->id);

        // loot list
        $lootList = array();


        while($row = $res->fetch_object()){

            $loot = new Item($row->item_id, $row);

            $loot->get_data();


            // loot chance default
            $lootChance = LOOT_CHANCE_DEFAULT;

            // type loot chance
            if(!empty(LOOT_CHANCE[$row->name])){

                $lootChance = LOOT_CHANCE[$row->name];
            }

            // custom loot chance
            if(!empty($loot->data->lootChance)){

                $lootChance = $loot->data->lootChance;
            }

            // equiped loot chance : half chance
            if($row->equiped){
                 // pnj will not drop equiped item
                if($this->id < 0){
                    $lootChance = 0;
                }else{
                    $lootChance = floor($lootChance / 2);
                }
            }else{
                // if pnj and not equiped, will drop everytime
                if($this->id < 0){
                    $lootChance = 100;
                }
            }

            // perform loot
            $nbLoot = 0;
            if ($lootChance >=100) {
                $nbLoot= $row->n;
            } else {
                for ($i = 0; $i < $row->n; $i++) {
                    if(random_int(1,100) <= $lootChance){
                        $nbLoot++;
                    }
                }
            }

            if ($nbLoot > 0) {
                $this->drop($loot, $nbLoot);
                // populate lootList
                $lootList[] = $loot->data->name .' x'. $nbLoot;
            }
        }

        if(count($lootList)){
            $text = $this->data->name .' a perdu des objets: '. implode(', ', $lootList) .'.';
            Log::put($this, $this, $text, type:"loot");
        }

        // spawn to hell
        $coords = (object) array('x'=>0,'y'=>0,'z'=>0,'plan'=>'enfers');

        $oneDayOfWalk = $this->caracs->mvt;
        $distance = $oneDayOfWalk * $this->data->rank;

        $possibleCoords = [
            ['x' => -1, 'y' => 0],
            ['x' => 1, 'y' => 0],
            ['x' => 0, 'y' => -1],
            ['x' => -1, 'y' => -1],
        ];

        $randomIndex = array_rand($possibleCoords);
        $randomCoords = $possibleCoords[$randomIndex];

        $coords->x = $randomCoords['x'] * $distance;
        $coords->y = $randomCoords['y'] * $distance;

        $this->go($coords);


        // purge malus
        $sql = 'UPDATE players SET malus = 0 WHERE id = ?';
        $db->exe($sql, $this->id);

        // purge effects & bonus
        $sql = '
        DELETE players_effects, players_bonus
        FROM players_effects
        JOIN players_bonus ON players_effects.player_id = players_bonus.player_id
        WHERE players_effects.player_id = ?
        ';
        $db->exe($sql, $this->id);

        // purge assists
        $values = array('target_id'=>$this->id);
        $db->delete('players_assists', $values);


        // refresh
        $this->refresh_view();
        $this->refresh_caracs();
        $this->refresh_data();
    }


    public function distribute_xp() {
        $return = array();
        $target_id = $this->id;
        $timeLimit = time() - ONE_DAY;

        // Récupérer les détails de la cible
        if(!isset($this->data)){
            $this->get_data();
        }

        // Calculer l'XP à distribuer - 0 si inactif, sinon rank * 10
        $target_rank = $this->data->rank;
        $xp_to_distribute = $this->data->isInactive ? 0 : ($target_rank * 10);
        $return['xp_to_distribute'] = $xp_to_distribute;

        self::clean_players_assists();

        // Récupérer les assists des dernières 24 heures pour cette cible
        $db = new Db();
        $sql = "
            SELECT player_id, player_rank, damages, time
            FROM players_assists
            WHERE target_id = ? AND time > ?
            ORDER BY time DESC
        ";

        $res = $db->exe($sql, array($target_id, $timeLimit));
        $assists = $res->fetch_all(MYSQLI_ASSOC);

        // Si la cible est inactif, donner 0 XP à tous les participants
        if($this->data->isInactive) {
            foreach($assists as $assist) {
                $return[$assist['player_id']] = 0;
            }
            return $return;
        }

        // Sinon, faire comme d'habitude
        $total_weight = 0;
        $weights = [];
        $xp_distribution = [];

        // Calculer les poids en fonction de la difference de rang et des dommages
        foreach ($assists as $assist) {
            $weight = ($target_rank / max(1, $assist['player_rank'])) * $assist['damages'];
            $weights[$assist['player_id']] = $weight;
            $total_weight += $weight;
        }

        if ($total_weight > 0) {
            // Distribuer l'XP selon les poids calculés
            $total_distributed_xp = 0;
            foreach ($weights as $player_id => $weight) {
                $xp_share = floor(($weight / $total_weight) * $xp_to_distribute);
                $xp_distribution[$player_id] = $xp_share;
                $total_distributed_xp += $xp_share;
            }

            // Calculer le reste d'XP
            $remaining_xp = $xp_to_distribute - $total_distributed_xp;

            // Ajouter le reste d'XP à la dernière personne qui a infligé des dommages
            if (!empty($assists) && $remaining_xp > 0) {
                $last_assist_player_id = $assists[0]['player_id'];
                $xp_distribution[$last_assist_player_id] += $remaining_xp;
            }

            // Mise à jour des XP des joueurs
            foreach ($xp_distribution as $player_id => $xp_share) {
                $return[$player_id] = $xp_share;
            }
        } else {
            // Si le poids total est à zero, distribuer l'XP équitablement entre les participants
            if (!empty($assists)) {
                $equal_xp_share = floor($xp_to_distribute / count($assists));
                foreach ($assists as $assist) {
                    $return[$assist['player_id']] = $equal_xp_share;
                }

                // Ajouter le reste d'XP (de la répartition) à la dernière personne qui a infligé des dommages
                $remaining_xp = $xp_to_distribute - ($equal_xp_share * count($assists));
                $return['remaining_xp'] = $remaining_xp;
            }
        }
        return $return;
    }


    public function check_share_factions($target){


        if(!isset($this->data)){

            $this->get_data();
        }

        if(!isset($target->data)){

            $target->get_data();
        }


        if($this->data->faction == $target->data->faction){

            return true;
        }


        if($this->data->secretFaction != "" && $target->data->secretFaction != ""){


            if($this->data->secretFaction == $target->data->secretFaction){


                return true;
            }
        }


        return false;
    }


    public function check_missive_permission($target){


        if(!isset($this->data)){

            $this->get_data();
        }

        if(!isset($target->data)){

            $target->get_data();
        }


        // same id not allowed
        if($this->id == $target->id){

            return false;
        }

        return true;
    }


    public function get_action_xp($target){


        if(!isset($this->data)){

            $this->get_data();
        }

        if(!isset($target->data)){

            $target->get_data();
        }


        $playerRank = $this->data->rank;
        $targetRank = $target->data->rank;


        $dif = $playerRank - $targetRank;


        $playerXp = ACTION_XP - $dif;


        if($playerXp < 1){

            $playerXp = 1;
        }


        if($this->id == $target->id){

            $playerXp = 1;
        }


        if($this->data->faction != '' && $this->data->faction == $target->data->faction){

            $playerXp = 1;
        }

        if($this->data->secretFaction != '' && $this->data->secretFaction == $target->data->secretFaction){

            $playerXp = 1;
        }
        if($target->data->isInactive){
            $playerXp = 1;
        }

        return $playerXp;
    }


    /*
     * STATIC FUNCTIONS
     */


    public static function put_player($name, $race, $pnj=false, $type='real') : int{


        $db = new Db();


        $goCoords = (object) array(
            'x'=>0,
            'y'=>0,
            'z'=>0,
            'plan'=>'gaia'
        );

        $coordsId = View::get_coords_id($goCoords);


        // Determine player type and generate IDs
        if ($pnj) {
            $type = 'npc';
        }

        $id = getNextEntityId($type);
        $displayId = getNextDisplayId($type);


        $raceJson = json()->decode('races', $race);


        $time = time();


        $values = array(
            'id'=>$id,
            'player_type'=>$type,
            'display_id'=>$displayId,
            'name'=>$name,
            'race'=>$race,
            'avatar'=>'img/avatars/ame/'. $race .'.webp',
            'portrait'=>'img/portraits/ame/1.jpeg',
            'coords_id'=>$coordsId,
            'faction'=>$raceJson->faction,
            'nextTurnTime'=>$time,
            'registerTime'=>$time
        );

        $res = $db->insert('players', $values);

        if (!$res) {
            exit('error inserting player');
        }

        // ID is already assigned via getNextEntityId()
        $player = new Player($id);

        // first init data
        $player->get_data();


        // add tuto action
        $player->add_action('tuto/attaquer');


        Player::refresh_list();


        if($pnj){

            //par défaut les pnjs sont créés en mode incognito
            $player->add_option('incognitoMode');

            return $id;
        }


        // first real player gets admin
        if($type == 'real' && $displayId == 1){

            $player->add_option('isAdmin');
        }

        // Enable action details by default for all new players
        $player->add_option('showActionDetails');

        Dialog::refresh_register_dialog();


        return $id;
    }

    public static function get_player_by_name($name){


        $db = new Db();

        // Filter by player_type='real' to prevent looking up tutorial players or NPCs
        // Used in exchanges, missives, and console commands
        $sql = '
        SELECT id FROM players WHERE name = ? AND player_type = "real"
        ';

        $res = $db->exe($sql, $name);

        if(!$res->num_rows){

            return false;
        }

        $row = $res->fetch_object();

        return new Player($row->id);
    }

    public function get_data(bool $forceRefresh=true){

        if(!$forceRefresh && isset($this->data)){

            return $this->data;
        }
        // first create dir
        if(!file_exists($_SERVER['DOCUMENT_ROOT'].'/datas/private/players/')){

            mkdir($_SERVER['DOCUMENT_ROOT'].'/datas/private/players/');
        }

        $playerJson = json()->decode('players', $this->id);


        // first player json
        if(!$playerJson){

            $this->get_row();

            // unset some unwanted var
            unset($this->row->psw);
            unset($this->row->mail);
            unset($this->row->ip);
            $pathInfo = pathinfo($this->row->portrait);
            $this->row->mini = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_mini.' . $pathInfo['extension'];
            $this->row->faction_img = 'img/factions/'. $this->row->faction .'.png';
            $this->row->faction_mini = 'img/factions/'. $this->row->faction .'_mini.png';

            $path = 'datas/private/players/'. $this->id .'.json';
            $data = Json::encode($this->row);

            Json::write_json($path, $data);

            $playerJson = json()->decode('players',  $this->id);
        }

        $this->data = $playerJson;

        // Set inactive status using playerService
        $this->data->isInactive = $this->id > 0 ? $this->playerService->isInactive($this->data->lastLoginTime) : false;

       


        return $playerJson;
    }

    //called by cron & register
    public static function refresh_list(){


        // CRITICAL: Filter by player_type to exclude tutorial players and NPCs from public lists
        // Only real players (player_type='real') should appear in rankings and leaderboards
        $sql = 'SELECT id,name,race,xp,rank,pr,faction,secretFaction,lastLoginTime FROM players WHERE player_type = "real" ORDER BY name';

        $db = new Db();

        $res = $db->exe($sql);

        $data = array();

        $list= array();
        $privateRaces = array();
        $firstData = null;
        while($row = $res->fetch_object()){

            $list[] = $row;
            if($row->id > 0 )
            {
                if(!isset($privateRaces[$row->race]))
                {
                    $privateRaces[$row->race]=file_exists(dirname(__FILE__) .'/../'.'datas/private/races/' . $row->race . '.json');
                    //echo $row->race . ":" . (($privateRaces[$row->race]) ? "private" :"public") . '<br>';
                }

                if($privateRaces[$row->race])continue;

                if(!$firstData || $row->xp > $firstData->xp){
                    $firstData = $row;
                }
            }
        }
        $data['list']=$list;
        $data['first']=$firstData;
        $data = Json::encode($data);

        Json::write_json('datas/private/players/list.json', $data);
    }
    
    public static function get_player_list(){
        
        $list = json()->decode('players', 'list');

        if(!$list){
            // refresh all classements (once per day, done with cron)

            Player::refresh_list();

            $list = json()->decode('players', 'list');

            $fileRankList = array('general','bourrins','reputation','fortunes');
            foreach($fileRankList as $file) {
                $filePath = 'datas/public/classements/'.$file.'.html';
                if (file_exists($filePath)) {
                    unlink($filePath); // Delete the file
                }
            }
        }
        return $list;
    }

    public static function clean_players_assists(){
        $timeLimit = time() - ONE_DAY;
        $db = new Db();
        $sql = 'DELETE FROM players_assists WHERE time < ?';
        $res = $db->exe($sql, array($timeLimit));
        return $res;
    }
}