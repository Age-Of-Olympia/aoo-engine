<?php
namespace Classes;

use App\Enum\EquipResult;
use App\Interface\ActorInterface;
use App\Service\ActionPassiveService;
use App\Service\PlayerActionsService;
use App\Service\PlayerOptionsService;
use App\Service\PlayerService;
use App\Service\MapService;
use App\Service\PlayerPassiveService;
use App\Service\EffectService;
use App\Service\PlayerEffectService;
use App\Service\PlayerBonusService;
use App\Service\RaceService;
use Exception;
use Throwable;

class Player implements ActorInterface {

    public function isSimulated(): bool { return false; }

    public $id;
    public object $data;
    public $caracs;
    public $upgrades;
    public $coords;
    public $nude;
    public $raceData;
    public $debuffs;
    public $buffs;
    public $turn;
    public $emplacements;
    public $row;
    public $playerService;
    public $playerReductionPassiveService;
    public $playerPassiveService;
    public $playerEffectService;
    public $effectService;
    public $playerBonusService;
    public $actionPassiveService;
    
    function __construct($playerId){

        $this->id = $playerId;

        $this->caracs = (object) array();
        $this->upgrades = (object) array();
        $this->emplacements = (object) array();
        $this->playerService = new PlayerService($playerId);
        $this->playerPassiveService = new PlayerPassiveService();
        $this->playerEffectService = new PlayerEffectService();
        $this->effectService = new EffectService();
        $this->playerBonusService = new PlayerBonusService();
        $this->actionPassiveService = new ActionPassiveService();

        /* L'esquive est calculée en fin de get_caracs() — la calculer
         * ici forçait le chargement complet des caracs (4+ requêtes) à
         * CHAQUE instanciation, y compris pour un simple avatar sur le
         * damier (~40 requêtes par affichage de la vue). */
    }

    public function getId(): int {
        return $this->id;
    }

    /**
     * Short, per-type sequential identifier shown to users (e.g. "mat.42").
     * Falls back to the raw id if display_id is unset on legacy rows or when
     * data hasn't been loaded yet.
     */
    public function getDisplayId(): int {
        if (!isset($this->data)) {
            $this->get_data();
        }
        $displayId = $this->data->display_id ?? 0;
        return $displayId > 0 ? (int) $displayId : (int) $this->id;
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

        /* Credentials live in `accounts`, the turn clock in `turns`, what is
         * earned in `progression`; the joins keep `->row` (and the JSON cache
         * built from it) carrying the same fields as before.
         *
         * NULLIF, not plain COALESCE: the backfill gives every character a row,
         * so an untouched satellite holds ''/0 — which would win over a
         * `players` column a path not yet routed through the services has just
         * written. Empty means "nothing here", and the column answers. */
        $sql = '
        SELECT
        p.*,
        COALESCE(NULLIF(a.psw, \'\'), p.psw) AS psw,
        COALESCE(NULLIF(a.mail, \'\'), p.mail) AS mail,
        COALESCE(NULLIF(a.plain_mail, \'\'), p.plain_mail) AS plain_mail,
        COALESCE(a.email_bonus, p.email_bonus) AS email_bonus,
        COALESCE(NULLIF(a.last_login_time, 0), p.lastLoginTime) AS lastLoginTime,
        COALESCE(NULLIF(t.next_turn_time, 0), p.nextTurnTime) AS nextTurnTime,
        COALESCE(NULLIF(t.last_action_time, 0), p.lastActionTime) AS lastActionTime,
        COALESCE(NULLIF(t.next_turn_rescheduled, 0), p.nextTurnRescheduled) AS nextTurnRescheduled,
        COALESCE(NULLIF(t.anti_berserk_time, 0), p.antiBerserkTime) AS antiBerserkTime,
        COALESCE(NULLIF(g.xp, 0), p.xp) AS xp,
        COALESCE(NULLIF(g.`rank`, 0), p.`rank`) AS `rank`,
        COALESCE(NULLIF(g.bonus_points, 0), p.bonus_points) AS bonus_points,
        COALESCE(NULLIF(g.pi, 0), p.pi) AS pi
        FROM
        players p
        LEFT JOIN accounts a ON a.player_id = p.id
        LEFT JOIN turns t ON t.player_id = p.id
        LEFT JOIN progression g ON g.player_id = p.id
        WHERE
        p.id = ?
        ';

        $res = $db->exe($sql, $this->id);


        if(!$res->num_rows){

            exit('error player id:'.strval($this->id));
        }


        $row = $res->fetch_object();


        $row->text = htmlentities($row->text);
        $row->story = htmlentities($row->story);


        $this->row = $row;
    }

    /**
     * Fold one equipped item's stat bonuses into a caracs object: every CARACS
     * trait the item defines is added (cc, ct, …), and a fixedF item overrides F.
     * Pure (no DB) so both get_caracs() and the simulator's SimulatedPlayer apply
     * equipment through the exact same path instead of duplicating the rule.
     */
    public static function applyItemCaracs(object $caracs, $item): void
    {
        foreach (CARACS as $k => $e) {
            if (!empty($item->data->$k)) {
                $caracs->$k += $item->data->$k;
            }
        }
        if (!empty($item->data->fixedF)) {
            $caracs->f = $item->data->fixedF;
        }
    }


    public function get_caracs(bool $nude=false): bool {


        if(!isset($this->data)){

            $this->get_data();
        }


        // Type block by discriminator: races for entities, items for item
        // exemplars. Both answer the same question, so nothing here branches.
        $this->raceData = (new \App\Service\EntityTypeCaracsService())
            ->ownCaracs($this->getPlayerType(), (string) $this->data->race);

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

            /* Un objet BRISÉ (ItemInstanceService::BROKEN_AT) reste
             * porté — visible à l'emplacement — mais ne contribue plus ses
             * caracs : c'est le sens gameplay de « brisé ». */
            if(isset($row->durability) && \App\Service\ItemInstanceService::isBroken((int) $row->durability)){

                continue;
            }

            self::applyItemCaracs($this->caracs, $item);
        }

        // Esquive
        if(!empty($item->data->esquive)){

            $this->caracs->esquive = (isset($this->caracs->esquive)) ? $this->caracs->esquive + $item->data->esquive : $item->data->esquive;
        }

        // elements de debuffs
        $effectsList = $this->playerEffectService->getEffectsByPlayerId($this->id);

        $this->debuffs = (object) array();
        $this->buffs = (object) array();

        $debuffCaracs = $this->effectService->getDebuffCaracs();
        $buffCaracs = $this->effectService->getBuffCaracs();

        foreach($effectsList as $e){


            if(!empty($debuffCaracs[$e->getName()])){


                $this->caracs->{$debuffCaracs[$e->getName()]} -= is_null($e->getValue()) ? 1 : $e->getValue();

                $this->debuffs->{$debuffCaracs[$e->getName()]} = $e->getName();
            }

            if(!empty($buffCaracs[$e->getName()])){


                $this->caracs->{$buffCaracs[$e->getName()]} += is_null($e->getValue()) ? 1 : $e->getValue();

                $this->buffs->{$buffCaracs[$e->getName()]} = $e->getName();
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
            // Some bonuses (pi, xp) are not in caracs, they're in data
            $baseValue = 0;
            if (isset($this->caracs->{$row->name})) {
                $baseValue = $this->caracs->{$row->name};
            } elseif (isset($this->data->{$row->name})) {
                $baseValue = $this->data->{$row->name};
            }
            $this->turn->{$row->name} = $baseValue + $row->n;
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


        /* Esquive (passifs à trait « esquive ») : fait partie des
         * caracs — calculée ici, elle est présente dès que les caracs
         * le sont, et part dans le cache .caracs.json. */
        $this->playerPassiveService->setEsquivePlayer($this);

        // save .caracs
        $data = Json::encode($this->caracs);
        Json::write_json('datas/private/players/'. $this->id .'.caracs.json', $data);
        return true;
    }

    public function setEsquive(int $esquive): void{
        $this->caracs->esquive = $esquive;
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


    /**
     * True when this player's current tile is of the given map type (e.g.
     * 'routes'). The engine's tile-reading instructions go through here so
     * SimulatedPlayer can override it with injected state instead of the DB —
     * see App\Action\OutcomeInstruction\TileTypeOutcomeInstruction.
     */
    public function isOnTileType(string $type): bool
    {
        $this->get_data(false);

        return (bool) (new MapService())->getTileTypeAtCoord($type, (int) $this->data->coords_id)->n;
    }

    /**
     * Where this entity stands: its own cell, or the cell of whatever holds it —
     * a sword in a hand is on its bearer's tile, a coin in a chest on the
     * chest's. Null when nothing in the chain stands anywhere: shelved off the
     * world, or held by something shelved.
     *
     * A pure read. {@see \App\Service\Map\EntityLocationService} owns both the
     * chain walk and the writing of a location; asking where something is never
     * puts it somewhere.
     *
     * Mémoïsé par défaut : la barre de statut, la minimap et la vue
     * relisaient chacune les coordonnées (3 requêtes par page). go()
     * invalide le memo après déplacement ; $refresh=true force la
     * relecture.
     */
    public function getCoords(bool $refresh = false): ?object{


        if (!$refresh && isset($this->coords)) {

            return $this->coords;
        }

        $this->get_data(false);

        /* db() and not the default connection: the legacy stack is pointed at
         * another database in places (the tutorial runs on its own), and the
         * chain walk has to read the same rows as the query below. */
        $coordsId = (new \App\Service\Map\EntityLocationService(db()))->cellOf((int) $this->id);

        if ($coordsId === null) {

            unset($this->coords);

            return null;
        }

        $db = new Db();

        $sql = '
            SELECT
            x, y, z, plan
            FROM
            coords
            WHERE
            id = ?
            ';

        $res = $db->exe($sql, $coordsId);

        $row = $res->fetch_object();

        if (!$row) {

            unset($this->coords);

            return null;
        }

        $this->coords = (object) array(
            'x' => $row->x,
            'y' => $row->y,
            'z' => $row->z,
            'plan' => $row->plan
        );


        return $this->coords;
    }


    public function move_player($coords){

        $this->go($coords);
    }


    /**
     * Last remnant of the old have/add/end/get god-method — kept only
     * because get_upgrades() above still calls $this->get('upgrades').
     * Options went to PlayerOptionsService (!371), actions to
     * PlayerActionsService (!374), effects always had their own
     * have_effect()/add_effect()/end_effect() shims backed by
     * PlayerEffectService. A future MR can inline the SELECT in
     * get_upgrades() and drop this too.
     */
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


    // options shortcuts — delegate to PlayerOptionsService.
    public function add_option($name){
        (new PlayerOptionsService())->addOption($this->id, $name);
    }
    public function have_option($name): int{ return (new PlayerOptionsService())->hasOption($this->id, $name); }
    public function end_option($name){
        (new PlayerOptionsService())->endOption($this->id, $name);
    }
    public function get_options(){ return (new PlayerOptionsService())->getOptions($this->id); }

    // actions shortcuts — delegate to PlayerActionsService.
    // The spell/technique → type='sort' branch lives inside the
    // service; see addAction().
    public function add_action($name){ (new PlayerActionsService())->addAction($this->id, $name); }
    public function have_action($name){ return (new PlayerActionsService())->hasAction($this->id, $name); }
    public function end_action($name){ (new PlayerActionsService())->endAction($this->id, $name); }
    public function get_actions(){ return (new PlayerActionsService())->getActions($this->id); }

    // passive actions shortcuts
    public function add_action_passive($name){ $this->playerPassiveService->addPassiveByPlayerId($this->id,$this->actionPassiveService->getIdByName($name)); }
    public function have_action_passive($name){ return $this->playerPassiveService->hasPassiveByPlayerId($this->id,$this->actionPassiveService->getIdByName($name)); }
    public function end_action_passive($name){ return $this->playerPassiveService->removePassiveByPlayerId($this->id,$this->actionPassiveService->getIdByName($name)); }

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

    public function get_passives(){


        $return = array();

        $sql = 'SELECT name FROM players_passives WHERE player_id = ?';

        $db = new Db();

        $res = $db->exe($sql, $this->id);

        while($row = $res->fetch_object()){

            $return[] = $row->passive_id;
        }

        return $return;
    }

    // effects
    public function have_effect(string $name): int{

        return $this->playerEffectService->hasEffectByPlayerIdByEffectName($this->id,$name);
    }

    /**
     * @param int $duration durée en TOURS. Le compteur est décrémenté à
     *        chaque tour du joueur et l'effet tombe à zéro.
     *        PlayerEffectService::DURATION_INFINITE pour un effet qui ne
     *        s'éteint jamais (vol des oiseaux, trait de race).
     */
    public function add_effect($name, $duration=1, int $value=1, bool $stackable=false): void{

        /* endTime porte désormais un NOMBRE DE TOURS, plus un instant.
         * Zéro n'est donc plus « illimité » mais « expiré » — l'infini
         * est une valeur négative, hors d'atteinte de la décrémentation
         * comme de la purge. */
        $endTime = (int) $duration;

        $this->playerEffectService->addEffectByPlayerId($this->id,$name,$endTime,$value, $stackable);

        // effect exists
        if(!$this->effectService->exists($name)){

            exit('error effect name');
        }

        // Annulations (ex-cycle élémentaire, désormais des listes) :
        // poser cet effet retire chaque effet qu'il annule ; s'il porte
        // déjà un effet qui L'annule, les deux tombent.
        foreach($this->effectService->getControlledEffects($name) as $controlled){

            if($this->have_effect($controlled)){

                $this->end_effect($controlled);
            }
        }

        foreach($this->effectService->getControllersOf($name) as $controller){

            if($this->have_effect($controller)){

                $this->end_effect($controller);
                $this->end_effect($name);
                break;
            }
        }
    }

    public function getEffects(): array{

        return $this->playerEffectService->getEffectsByPlayerId($this->id);
    }

    public function getEffectValue($name){

        return $this->playerEffectService->getEffectValueByPlayerIdByEffectName($this->id,$name);
    }

    public function end_effect(string $name): void{

        $this->playerEffectService->removeEffectByPlayerId($this->id,$name);
    }

    public function purge_effects(): int{


        $sql = '
        DELETE
        FROM
        players_effects
        WHERE
        player_id = ?
        AND
        endTime = 0
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
        endTime = 0
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


        /* getCoords() est mémoïsé : $this->coords garde volontairement
         * les ANCIENNES coordonnées jusqu'à la fin de go() (le
         * rafraîchissement SVG de l'ancien plan en dépend), puis go()
         * pose lui-même les nouvelles ($this->coords = $goCoords). */
        $sql = 'UPDATE players SET coords_id = ? WHERE id = ?';

        $db = new Db();
        $db->exe($sql, array($coordsId, $this->id));

        /* L'emprise suit le pas. Elle ne se lit pas encore (L3 la pose, L4
         * la branchera), mais une ancre laissée derrière ferait démarrer la
         * suite d'une carte fausse. */
        (new \App\Service\Map\EntityCellService())->syncCells((int) $this->id);


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


            /* Un élément de carte foulé applique son effet pour UN tour
             * (l'élément, lui, reste daté : voir Element::put). */
            $this->add_effect($row->name, 1);
        }


        // void plan
        $planJson = plans()->read($this->coords->plan);

        if(!$planJson){
            $this->refresh_view();
        }
        else{
            View::refresh_players_svg($this->coords);
        }

        if ($goCoords->plan != $this->coords->plan || $zChange) {
            $goPlanJson = plans()->read($goCoords->plan);
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

    public static function CapPI($playerXp,$xpGained,$xpCap): int{

        // Ajout d'un cap temporaire des PIs pour la fin de la saison
        if($xpGained>0){
            return min(max(0,($xpCap - $playerXp)),$xpGained);
        }
        else{
            return max(min(0,$playerXp-$xpCap+$xpGained) ,$xpGained);
        }
    }

    public function put_xp($xp){


        if(!isset($this->data)){

            $this->get_data();
        }

        $pi = Player::CapPI(playerXp:$this->data->xp,xpGained:$xp,xpCap:SEASON_XP);

        $this->data->xp += $xp;
        $this->data->pi += $pi;

        // update rank
        $rank = Str::get_rank($this->data->xp);

        (new \App\Service\ProgressionService())->gain((int) $this->id, $xp, $pi, $rank);


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
        $file = self::cachePath((int) $this->id, '.svg');
        if (is_file($file)) {
            unlink($file); // Delete the file
        }
    }

    /**
     * Chemin d'un cache par-entité ({id}{suffix}) — résolu depuis la
     * racine du projet, PAS depuis DOCUMENT_ROOT : vide en CLI, il
     * rendait les refresh_*() muets hors web (caches fantômes dans les
     * tests et les scripts).
     */
    public static function cachePath(int $playerId, string $suffix): string
    {
        $root = ($_SERVER['DOCUMENT_ROOT'] ?? '') !== '' ? $_SERVER['DOCUMENT_ROOT'] : dirname(__DIR__);

        return $root . '/datas/private/players/' . $playerId . $suffix;
    }

    public function refresh_data(){
        $file = self::cachePath((int) $this->id, '.json');
        if (is_file($file)) {
            unlink($file);
        }
        // Le décodeur JSON garde un cache mémoire par process : sans cet
        // oubli, un process long (tests, scripts) ressert l'ancien état.
        json()->forget('players', (string) $this->id);
    }

    public function refresh_invent(){
        $file = self::cachePath((int) $this->id, '.invent.html');
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


        $this->data->pf = max(0, $this->data->pf + $pf);

        // Floored at zero: faith is spent, never owed.
        $sql = 'UPDATE players SET pf = GREATEST(pf + ?, 0) WHERE id = ?';

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

            (new \App\Service\ProgressionService())->addPi((int) $this->id, (int) $total_pi_rembouser);


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

                    /* Ce que l'entité VERSE au sol en cas de blessure :
                     * configurable par race (races.bleeds) — '' = rien,
                     * un mur ne saigne pas. */
                    $bleeds = (new RaceService())->getRaceByName((string) ($this->data->race ?? ''))?->getBleeds() ?? '';

                    if($bleeds !== ''){

                        Element::put($bleeds, $this->data->coords_id);
                    }
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


        /* Ménage des lignes vidées de leur sens : un déficit de PV ou de PM
         * revenu à zéro vaut l'absence de ligne.
         *
         * Le player_id manquait. Le DELETE portait donc sur TOUTE la table,
         * pour tous les personnages et toutes les entités, à chaque coup
         * porté, chaque soin, chaque repos, chaque coût de mouvement — soit
         * un balayage complet (aucun index sur `name` seul, la clé primaire
         * est (player_id, name)) sur le chemin le plus fréquenté du jeu.
         *
         * Sans conséquence de jeu jusqu'ici, parce qu'il ne supprimait que
         * des lignes déjà équivalentes à rien, et que putBonus plafonne le
         * soin au déficit. Mais le coût grandit avec le nombre d'entités
         * blessées, et c'est justement ce que l'entification multiplie.
         *
         * Forme identique à celle d'applyUnequipItemBonus, qui fait le même
         * ménage correctement scopé. */
        $sql = '
        DELETE FROM
        players_bonus
        WHERE
        player_id = ?
        AND
        name IN ("pm", "pv")
        AND
        n >= 0
        ';

        $db->exe($sql, array($this->id));


        $this->refresh_caracs();


        /* Structures switch sprite: _broken below half PV, base above.
         * Central here because every path — damage and healing alike — goes
         * through putBonus. */
        if (array_key_exists('pv', (array) $bonus)
            && in_array($this->getPlayerType(), ['building', 'scenery'], true)) {

            (new \App\Service\BuildingService())->refreshWoundSprite($this->id);
        }


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

        // Une structure (bâtiment, objet unique) n'a pas de malus : il
        // pénalise les jets de défense, et elle n'esquive jamais — même
        // logique que le saignement (races.bleeds).
        if (\App\Enum\EntityCategory::fromPlayerType($this->getPlayerType()) === \App\Enum\EntityCategory::Structure) {

            return;
        }

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


    /* Every gold spend goes through here: the service pays in one
     * conditional write and answers whether it happened. Reading the
     * purse then subtracting lets two concurrent spends both pass. */
    public function spendGold(int $cost, bool $bank = false): bool
    {
        if (!(new \App\Service\GoldService())->spend((int) $this->id, $cost, $bank)) {

            return false;
        }

        if (!$bank) {

            $this->refresh_invent();
        }

        return true;
    }


    public function drop($item, $n){


        if(!isset($this->data)){

            $this->get_data();
        }

        /* Décrémenter la PILE d'abord et vérifier le retour : add_item
         * refuse quand la pile ne couvre pas n (possession en instances
         * seulement) — poser la bourse au sol AVANT créerait l'objet à
         * partir de rien (duplication au ramassage). */
        if(!$item->add_item($this, -$n)){

            exit('error drop n');
        }

        /* One stack per (tile, item): the unique key merges repeated
         * drops into the tile's existing line. */
        $db = new Db();

        $db->exe(
            'INSERT INTO map_items (item_id, coords_id, n) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE n = n + VALUES(n)',
            [$item->id, $this->data->coords_id, $n]
        );
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


    /**
     * Pose un suivant — l'étal du marchand, le double d'illusion.
     *
     * Le suivant porte son nom et sa case ; il ne passe plus par une ligne de
     * décor. L'ancienne version en posait une au nom CODÉ EN DUR (`marchand`,
     * quel que soit le suivant demandé) puis la relisait par son nom, ce qui
     * fatalisait pour tout autre suivant et pouvait, pour le marchand,
     * ADOPTER un décor déjà présent sur la case — que la dépose supprimait
     * ensuite. Sur la carte de production, 19 décors `marchand` n'appartiennent
     * à personne et étaient à portée de ce geste.
     */
    public function add_follower($name, $params){

        $db = new Db();

        $db->insert('players_followers', array(
            'player_id' => $this->id,
            'name'      => $name,
            'coords_id' => $this->data->coords_id,
            'params'    => $params
        ));
    }

    /**
     * Retire un suivant. Le décor de la carte n'est plus concerné : c'est
     * précisément ce qui faisait disparaître des décors d'animateur.
     */
    public function delete_follower($name){

        (new Db())->exe(
            'DELETE FROM players_followers WHERE player_id = ? AND name = ?',
            array($this->id, $name)
        );
    }


    /**
     * Le suivant emboîte le pas.
     *
     * `params` dit où il se tient : `on` sur la case où le porteur ARRIVE,
     * `last` sur celle qu'il quitte — un étal reste en arrière, un double
     * marche avec vous. Appelé par go() AVANT que players.coords_id ne change,
     * d'où $this->data->coords_id qui vaut encore l'ancienne case.
     */
    public function move_followers($coordsId){

        $db = new Db();

        $db->exe(
            'UPDATE players_followers SET coords_id = ? WHERE player_id = ? AND params = ?',
            array($coordsId, $this->id, 'on')
        );

        $db->exe(
            'UPDATE players_followers SET coords_id = ? WHERE player_id = ? AND params = ?',
            array($this->data->coords_id, $this->id, 'last')
        );
    }

    


    /**
     * @param int|null $instanceId instance PRÉCISE à équiper (clic sur une
     *        ligne d'instance de l'inventaire) — null : la plus ancienne
     *        disponible, sinon promotion depuis la pile
     */
    /**
     * @param bool|null $clickedEquippedLine la ligne d'inventaire cliquée
     *        était-elle PORTÉE ? true = geste de déséquipement, false =
     *        geste d'équipement (même si un AUTRE exemplaire du même
     *        objet catalogue est porté : équiper l'arc neuf remplace
     *        l'abîmé au lieu de le déséquiper). Null = pas de contexte
     *        de ligne (mort, désarmement, revert Ae, munitions auto) :
     *        bascule héritée par objet catalogue.
     */
    public function equip(Item $item, bool $doNotRefresh = false, ?int $instanceId = null, ?bool $clickedEquippedLine = null): EquipResult{

        $db = new Db();


        if(!isset($item->data)){

            $item->get_data();
        }


        if($item->row->name == 'poing'){

            return EquipResult::DoNothing;
        }


        $itemList = Item::get_equiped_list($this);


        if(!empty($itemList[$item->id]) && $clickedEquippedLine !== false){


            // item is cursed
            if($item->row->cursed){

                echo '<div id="data">Objet Maudit !</div>';
                return EquipResult::Cursed;
            }

            // item is equiped : UNEQUIP

            if (!empty($itemList[$item->id]->instance_id)) {

                // L'objet équipé est une instance — la déséquiper
                // (une instance encore vierge retourne silencieusement en pile).
                (new \App\Service\ItemInstanceService())
                    ->unequipInstance((int) $itemList[$item->id]->instance_id);
            } else {

                // Ligne de pile héritée (antérieure à la conversion 1d).
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
            }

            $this->applyUnequipItemBonus($item);

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

            // Garde anti no-op héritée — court-circuitée quand le geste
            // d'équipement est explicite (clic sur une ligne NON portée) :
            // équiper l'arc neuf alors que l'abîmé occupe l'emplacement
            // est un REMPLACEMENT voulu, pas un no-op.
            if($clickedEquippedLine !== false
                && !empty($this->emplacements->{$item->data->emplacement}) && $this->emplacements->{$item->data->emplacement}->id == $item->id){
                return EquipResult::DoNothing;
            }


            if(!Item::get_free_emplacement($this)){
                if($item->data->emplacement != 'munition' && $item->data->emplacement != 'doigt'){
                    return EquipResult::NoRoom;
                }
            }


            // cursed emp — piles héritées ET instances
            $sql = '
            SELECT
            (SELECT COUNT(*) FROM items AS i
             INNER JOIN players_items AS p ON i.id = p.item_id
             WHERE p.player_id = ? AND p.equiped = ? AND i.cursed = 1)
            +
            (SELECT COUNT(*) FROM items AS i
             INNER JOIN item_instances AS ii ON ii.item_id = i.id
             INNER JOIN players AS e ON e.id = ii.entity_id
             WHERE e.holder_id = ? AND e.slot = ? AND i.cursed = 1)
            AS n
            ';

            $res = $db->exe($sql, array($this->id, $item->data->emplacement, $this->id, $item->data->emplacement));

            $row = $res->fetch_object();

            if($row->n){

                echo '<div id="data">Objet Maudit!</div>';
                return EquipResult::Cursed;
            }


            // Disponibilité AVANT toute mutation : un échec du chemin
            // instance APRÈS la libération des emplacements laisserait des
            // objets déséquipés en base alors que l'appelant croit qu'il ne
            // s'est rien passé (l'objet visé n'est pas équipé ici — la
            // branche UNEQUIP l'aurait intercepté — donc libérer des
            // emplacements ne peut pas le rendre disponible).
            $instanceService = new \App\Service\ItemInstanceService();
            $available = $instanceId !== null
                ? $instanceService->isInstanceEquippable($this->id, (int) $item->id, $instanceId)
                : $instanceService->hasEquippableUnit($this->id, (int) $item->id);
            if($item->data->emplacement != 'munition' && $item->data->emplacement != 'trophee'
                && !$available){

                return EquipResult::DoNothing;
            }

            // Libérer les emplacements des deux représentations —
            // piles héritées (SQL) et instances (service, avec retour en pile
            // des instances encore vierges).
            $emplacementsToClear = [$item->data->emplacement];
            if($item->data->emplacement == "deuxmains"){
                $emplacementsToClear[] = 'main1';
                $emplacementsToClear[] = 'main2';
            }
            elseif($item->data->emplacement == "main1" || $item->data->emplacement == "main2"){
                $emplacementsToClear[] = 'deuxmains';
            }

            $sql = '
            UPDATE
            players_items
            SET
            equiped = ""
            WHERE
            player_id = ?
            AND
            equiped IN('. Db::print_in($emplacementsToClear) .')
            ';

            $db->exe($sql, array_merge(array($this->id), $emplacementsToClear));

            $instanceService->unequipEmplacements($this->id, $emplacementsToClear);

            if($item->data->emplacement == 'munition' || $item->data->emplacement == 'trophee'){

                // Sémantique de PILE conservée : tout le carquois est équipé
                // d'un bloc et consommé unité par unité (RequiresAmmo) — une
                // instance par flèche n'aurait aucun sens.
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
            }
            else{

                // Équiper crée (ou réutilise) une INSTANCE — l'objet
                // commence à exister individuellement au moment où il est porté.
                try {
                    $instanceService->equipCatalogItem($this->id, (int) $item->id, $item->data->emplacement, $instanceId);
                } catch (\RuntimeException) {
                    return EquipResult::DoNothing;
                }
            }

            $this->applyEquipItemBonus($item);

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

    public function applyEquipItemBonus(Item $item): void {
        $db = new Db();

        // Associe chaque bonus possible à sa valeur si elle existe
        $bonusMap = [
            'mvt' => $item->data->mvt ?? null,
            'pv'  => $item->data->pv ?? null,
            'pm'  => $item->data->pm ?? null,
        ];

        foreach ($bonusMap as $name => $value) {
            if ($value !== null) {

                // Crée la ligne si elle n'existe pas encore
                $sql = '
                    INSERT IGNORE INTO players_bonus (player_id, name, n)
                    VALUES (?, ?, 0)
                ';
                $db->exe($sql, [$this->id, $name]);

                // Met à jour la valeur (soustrait le bonus)
                $sql = '
                    UPDATE players_bonus
                    SET n = n - ?
                    WHERE player_id = ? AND name = ?
                ';
                $db->exe($sql, [(float)$value, $this->id, $name]);
            }
        }
    }

    public function applyUnequipItemBonus(Item $item): void {
        $db = new Db();

        $bonusMap = [
            'mvt' => $item->data->mvt ?? null,
            'pv'  => $item->data->pv ?? null,
            'pm'  => $item->data->pm ?? null,
        ];

        foreach ($bonusMap as $name => $value) {
            if ($value !== null) {
                // On réajoute le bonus
                $sql = '
                    INSERT INTO players_bonus (player_id, name, n)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE n = n + VALUES(n)
                ';
                $db->exe($sql, [$this->id, $name, (float)$value]);

                // On supprime la ligne si le bonus devient nul ou positif
                $sql = '
                    DELETE FROM players_bonus
                    WHERE player_id = ? AND name = ? AND n >= 0
                ';
                $db->exe($sql, [$this->id, $name]);
            }
        }
    }
    public function getRace(): string
    {
        $this->get_data(false);
        return $this->data->race;
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

        /* Le butin part d'abord : ce que l'entité possédait tombe au sol,
         * chaque objet selon sa chance. Le geste vit dans son propre service
         * — un coffre qu'on casse répand son contenu de la même façon, sans
         * rien connaître des enfers ni des effets à purger. */
        (new \App\Service\LootSpillService())->spill($this);

        $db = new Db();

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

        /* Une structure (bâtiment, objet unique) n'est pas un
         * interlocuteur : ni destinataire ni expéditeur de missives —
         * le courrier s'entassait dans une boîte que personne n'ouvre. */
        foreach ([$this, $target] as $side) {
            $category = \App\Enum\EntityCategory::fromPlayerType($side->data->player_type ?? 'real');
            if (!$category->isSocialActor()) {

                return false;
            }
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


    /**
     * Create a `real` or `npc` row in the `players` table.
     *
     * NOTE: this path does NOT populate the tutorial FK columns
     * (`tutorial_session_id`, `real_player_id_ref`). Tutorial player
     * creation must go through `App\Factory\TutorialPlayerFactory::create()`,
     * which is the only writer that sets `player_type='tutorial'` and both
     * FK columns atomically. Calling `put_player()` for a tutorial row would
     * leave it orphaned from its owning real player (FK guardrail).
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


        $raceData = (new RaceService())->getRaceData($race);


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
            // On a cell means installed on it: an empty slot there would read
            // as "lying on the floor" once items became entities.
            'slot'=>\App\Service\Map\EntityLocationService::SLOT_INSTALLED,
            'faction'=>$raceData->faction ?? '',
            'nextTurnTime'=>$time,
            'registerTime'=>$time
        );

        $res = $db->insert('players', $values);

        if (!$res) {
            exit('error inserting player');
        }

        /* Le personnage naît avec son emprise — une case, son ancre. */
        (new \App\Service\Map\EntityCellService())->syncCells((int) $id);

        // ID is already assigned via getNextEntityId()
        $player = new Player($id);

        // first init data
        $player->get_data();


        // Grant the race's starter pack (race_starter_actions in DB).
        (new PlayerActionsService())->grantRaceStarterPack($id, $race);


        Player::refresh_list();


        if($pnj){

            //par défaut les pnjs sont créés en mode incognito
            $player->add_option('incognitoMode');

            return $id;
        }


        // Bootstrap admin grant: only the very first row ever inserted
        // into the players table — players.id === 1 with player_type
        // 'real' — gets isAdmin. Anything else MUST NOT.
        //
        // Previous gate `$displayId == 1` matched whenever
        // MAX(display_id) was NULL (any env that ran the tutorial
        // migration without backfilling display_id on legacy rows),
        // so the next real registration silently became admin. Pinning
        // on the primary-key id removes that escalation surface
        // entirely: in prod, id=1 already exists, and getNextEntityId
        // never re-issues it.
        if ($type === 'real' && $id === 1) {
            $player->add_option('isAdmin');
        }

        // Enable action details by default for all new players
        $player->add_option('showActionDetails');

        /* Bordure de race réservée aux personnages par défaut : sur un
         * mur ou un coffre, le liseré encombre le décor sans rien
         * apprendre. Ceux qui la veulent partout la réactivent dans le
         * popover « Affichage ». */
        $player->add_option('hideStructureBorders');

        Dialog::refresh_register_dialog();


        return $id;
    }

    /**
     * @deprecated Use PlayerFactory::legacyByName() — returns ?Player
     * instead of Player|false. This method remains as the implementation
     * backing the factory; new callers should go through the factory.
     */
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
    public static function get_player_by_id($id){


        $db = new Db();

        $sql = '
        SELECT id FROM players WHERE id = ?
        ';

        $res = $db->exe($sql, $id);

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
        if(!file_exists(dirname(self::cachePath(0, '')))){

            mkdir(dirname(self::cachePath(0, '')));
        }

        $playerJson = json()->decode('players', $this->id);


        // first player json
        if(!$playerJson){

            $this->get_row();

            // unset some unwanted var
            unset($this->row->psw);
            unset($this->row->mail);
            unset($this->row->ip);
            // Portrait vide (structure sans visuel, repli initiales au
            // rendu) : pas de déclinaison _mini à dériver.
            if ($this->row->portrait !== '' && $this->row->portrait !== null) {
                $pathInfo = pathinfo($this->row->portrait);
                $this->row->mini = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_mini.' . $pathInfo['extension'];
            } else {
                $this->row->mini = '';
            }
            $this->row->faction_img = 'img/factions/'. $this->row->faction .'.png';
            $this->row->faction_mini = 'img/factions/'. $this->row->faction .'_mini.png';

            $path = 'datas/private/players/'. $this->id .'.json';
            $data = Json::encode($this->row);

            Json::write_json($path, $data);

            $playerJson = json()->decode('players',  $this->id);
        }

        $this->data = $playerJson;

        // L'inactivité n'a de sens que pour un JOUEUR RÉEL (dernière
        // connexion) : jamais pour un PNJ, un personnage de tutoriel ou
        // une entité structure (bâtiment/objet unique, lastLoginTime 0
        // — ils ressortaient « inactifs »).
        $this->data->isInactive = ($this->id > 0 && ($this->data->player_type ?? 'real') === 'real')
            ? $this->playerService->isInactive($this->data->lastLoginTime)
            : false;

       


        return $playerJson;
    }

    //called by cron & register
    public static function refresh_list(){


        // CRITICAL: Filter by player_type to exclude tutorial players and NPCs from public lists
        // Only real players (player_type='real') should appear in rankings and leaderboards
        $sql = 'SELECT id,display_id,name,race,xp,rank,pr,faction,secretFaction,lastLoginTime FROM players WHERE player_type = "real" ORDER BY name';

        $db = new Db();

        $res = $db->exe($sql);

        $data = array();

        $list= array();
        $hiddenRaces = array();
        $raceService = new RaceService();
        $firstData = null;
        while($row = $res->fetch_object()){

            $list[] = $row;
            if($row->id > 0 )
            {
                if(!isset($hiddenRaces[$row->race]))
                {
                    // Hidden races (ex-private race JSON) stay out of the public first-player spot.
                    $race = $raceService->getRaceByName($row->race);
                    $hiddenRaces[$row->race] = $race !== null && $race->getHidden();
                }

                if($hiddenRaces[$row->race])continue;

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

    public function getPassives(int $playerId): array{
        return $this->playerPassiveService->getPassivesByPlayerId($playerId);
    }

    public function getEquipedItems(): array {
        return Item::get_equiped_list($this);
    }

    public function hasMagicalItemEquipped(): bool
    {
        foreach ($this->getEquipedItems() as $item) {
            $item = new Item($item->id, $item);
            if ($item->isMagical()) {
                return true;
            }
        }

        return false;
    }

    public function getEquipedItemsEffects(): array {
        $equipedItems = $this->getEquipedItems();
        $effectsList = [];
        foreach ($equipedItems as $item) {
            $item = new Item($item->id, $item);
            $effectsList = array_merge($effectsList, $item->getItemEffects());
        }

        return $effectsList;
    }

    public function getPush(Player $target): bool {
        $att = $this->caracs->f;
        $def = max($target->caracs->e + 4,$target->caracs->agi);
        $pv = floor($target->getRemaining('pv')/10);
        // Modificateurs de poussée portés par les effets (catalogue :
        // push_attack_mod / push_defense_mod — ex-renforcement,
        // stabilite, instabilite codés en dur).
        $attMods = $this->effectService->modifierContributions($this->getEffects(), 'getPushAttackMod');
        $defMods = $this->effectService->modifierContributions($target->getEffects(), 'getPushDefenseMod');
        return $att + $attMods['pos'] - $attMods['neg'] >= $def + $pv + $defMods['pos'] - $defMods['neg'];
    }
}