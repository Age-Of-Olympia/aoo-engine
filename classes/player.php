<?php

class Player{


    function __construct($playerId){

        $this->id = $playerId;

        $this->caracs = (object) array();
        $this->upgrades = (object) array();
    }


    public function get_row(){


        $db = new Db();

        $res = $db->get_single('players', $this->id);


        if(!$res->num_rows){

            exit('error player id');
        }


        $row = $res->fetch_object();


        $row->text = htmlentities($row->text);
        $row->story = htmlentities($row->story);


        $this->row = $row;
    }


    public function get_caracs($nude=false){


        if(!isset($this->data)){

            $this->get_data();
        }


        $raceJson = json()->decode('races', $this->data->race);

        $this->raceData = $raceJson;

        $this->get_upgrades();

        foreach(CARACS as $k=>$e){

            $this->caracs->$k = $this->raceData->$k + $this->upgrades->$k;
        }


        if($nude){

            return false;
        }


        $this->nude = clone $this->caracs;


        $itemList = Item::get_equiped_list($this);

        foreach($itemList as $row){


            $item = new Item($row->id, $row);

            $item->get_data();


            $this->{$row->equiped} = $item;


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


        // elements de debuffs
        $effectsList = $this->get_effects();

        $this->debuffs = (object) array();


        foreach($effectsList as $e){


            if(!empty(ELE_DEBUFFS[$e])){


                $this->caracs->{ELE_DEBUFFS[$e]} -= 1;

                $this->debuffs->{ELE_DEBUFFS[$e]} = $e;
            }
        }


        // fist
        if(!isset($this->main1)){


            $item = Item::get_item_by_name('poing');

            $item->get_data();

            $this->main1 = (object) array();
            $this->main1 = $item;
        }


        // save .caracs
        $data = Json::encode($this->caracs);
        Json::write_json('datas/private/players/'. $this->id .'.caracs.json', $data);
    }


    public function get_caracsJson(){


        if(!$caracsJson = json()->decode('players', $this->id .'.caracs')){

            $this->get_caracs();

            $caracsJson = json()->decode('players', $this->id .'.caracs');
        }

        return $caracsJson;
    }


    public function get_turnJson(){


        if(!$turnJson = json()->decode('players', $this->id .'.turn')){

            $this->get_caracs();

            $turnJson = json()->decode('players', $this->id .'.turn');
        }

        return $turnJson;
    }


    public function get_upgrades(){


        foreach(CARACS as $k=>$e){

            $this->upgrades->$k = 0;
        }

        foreach($this->get('upgrades') as $e){

            $this->upgrades->$e += 1;
        }

        return $this->upgrades;
    }


    public function get_coords(){


        $db = new Db();


        if(!isset($this->data)){

            $this->get_data();
        }


        // first coords
        if($this->data->coords_id == NULL){


            $coords = (object) array(
                'x'=>0,
                'y'=>0,
                'z'=>0,
                'plan'=>'olympia'
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
            'x'=>$row->x,
            'y'=>$row->y,
            'z'=>$row->z,
            'plan'=>$row->plan
        );

        $this->coords = $coords;

        return $coords;
    }


    public function move_player($coords){

        $this->go($coords);
    }


    // have/add/end/get main functions
    public function have($table, $name){


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


        $values = array(
            'player_id'=>$this->id,
            'name'=>$name
        );


        if(!empty($charges)){

            $values['charges'] = $charges;
        }


        if($table == 'actions'){

            $actionJson = json()->decode('actions', $name);

            if(!empty($actionJson->type)){

                $values['type'] = $actionJson->type;
            }
        }


        $db = new Db();

        $db->insert('players_'. $table, $values);
    }

    public function end($table, $name){

        $values = array(
            'player_id'=>$this->id,
            'name'=>$name
        );

        $db = new Db();

        $db->delete('players_'. $table, $values);
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
    public function have_option($name){ return $this->have('options', $name); }
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
    public function have_effect($name){

        return $this->have('effects', $name);
    }

    public function add_effect($name, $duration=0){


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

            if($this->have_effect(ELE_CONTROLS[$name])){

                $this->end_effect(ELE_CONTROLS[$name]);

                echo '<script>alert("'. ucfirst($name) .' annule '. ucfirst(ELE_CONTROLS[$name]) .'");document.location.reload();</script>';
            }

            if(!empty(ELE_IS_CONTROLED[$name])){


                if($this->have_effect(ELE_IS_CONTROLED[$name])){

                    $this->end_effect(ELE_IS_CONTROLED[$name]);
                    $this->end_effect($name);

                    echo '<script>alert("'. ucfirst(ELE_IS_CONTROLED[$name]) .' et '. ucfirst($name) .' s\'annulent!");document.location.reload();</script>';
                }
            }
        }
    }

    public function get_effects(){

        return $this->get('effects');
    }

    public function end_effect($name){


        $values = array(
            'player_id'=>$this->id,
            'name'=>$name
        );

        $db = new Db();

        $db->delete('players_effects', $values);
    }

    public function purge_effects(){


        $sql = '
        DELETE
        FROM
        players_effects
        WHERE
        player_id = ?
        AND
        endTime <=  '. time() .'
        AND
        endTime != 0
        ';

        $db = new Db();

        $db->exe($sql, $this->id);
    }


    public function go($goCoords){


        $db = new Db();


        $coordsId = View::get_coords_id($goCoords);


        $this->move_followers($coordsId);


        $sql = 'UPDATE players SET coords_id = ? WHERE id = ?';

        $db->exe($sql, array($coordsId, $this->id));

        $this->refresh_data();


        // add elements
        $sql = 'SELECT name FROM map_elements WHERE coords_id = ?';

        $res = $db->exe($sql, $coordsId);

        while($row = $res->fetch_object()){


            // fishing
            if($row->name == 'eau'){


                $item = Item::get_item_by_name('canne_a_peche');


                if(FISHING || ($item && $item->get_n($this))){


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


            $this->add_effect($row->name, ONE_DAY);
        }


        // void plan
        $planJson = json()->decode('plans', $this->coords->plan);

        if(!$planJson){


            $this->refresh_view();
        }
        else{


            View::refresh_players_svg($this->coords);
        }


        $this->refresh_caracs();

       // delete empty coords will be cron managed for easier debugging
    }


    public function put_xp($xp){


        if(!isset($this->data)){

            $this->get_data();
        }


        $this->data->xp += $xp;
        $this->data->pi += $xp;


        // update rank
        $rank = Str::get_rank($this->data->xp);

        $sql = 'UPDATE players SET xp = xp + ?, pi = pi + ?, rank = ? WHERE id = ?';

        $db = new Db();

        $db->exe($sql, array($xp, $xp, $rank, $this->id));


        $this->refresh_data();
    }


    public function put_pr($pr){


        if(!isset($this->data)){

            $this->get_data();
        }


        for($n=$this->data->pr; $n<=$this->data->pr+$pr; $n++){

            if($n %5 == 0){

                Forum::put_reward($this);
            }
        }


        $sql = 'UPDATE players SET pr = pr + ? WHERE id = ?';

        $db = new Db();

        $db->exe($sql, array($pr, $this->id));


        $this->refresh_data();
    }


    public function put_kill($target, $xp, $assist=0){


        $db = new Db();

        $values = array(
            'player_id'=>$this->id,
            'target_id'=>$target->id,
            'player_rank'=>$this->data->rank,
            'target_rank'=>$target->data->rank,
            'xp'=>$xp,
            'assist'=>$assist,
            'time'=>time(),
            'plan'=>$target->coords->plan
        );

        $db->insert('players_kills', $values);

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
        damages = damages + VALUES(damages);
        ';

        $db->exe($sql);
    }


    public function refresh_view(){

        @unlink('datas/private/players/'. $this->id .'.svg');
    }

    public function refresh_data(){

        @unlink('datas/private/players/'. $this->id .'.json');
    }

    public function refresh_invent(){

        @unlink('datas/private/players/'. $this->id .'.invent.html');
    }

    public function refresh_kills(){

        @unlink('datas/private/players/'. $this->id .'.kills.html');
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


    public function put_bonus($bonus) : bool{


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


            if($carac == 'a' && $val < 0){


                $this->put_fat(FAT_PER_ACTION);
            }

            elseif($carac == 'pv'){


                if($val < 0){


                    $this->put_malus(MALUS_PER_DAMAGES);

                    // add blood on floor
                    Element::put('sang', $this->data->coords_id);
                }

                elseif($val > 0){


                    $pvLeft = $this->get_left('pv');

                    if($pvLeft + $val > $this->caracs->pv){

                        $val = $pvLeft;
                    }
                }
            }

            elseif($carac == 'pm' && $val > 0){


                $pmLeft = $this->get_left('pm');

                if($pmLeft + $val > $this->caracs->pm){

                    $val = $pmLeft;
                }
            }
        }

        $sql = '
        INSERT INTO
        players_bonus
        (`player_id`,`name`,`n`)
        VALUE'. implode(',', $values) .'
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


        return true;
    }


    public function get_left($carac){


        if(!isset($this->caracs)){


            $this->get_caracs();
        }



        if(!isset($this->turn->$carac)){


            return $this->caracs->$carac;
        }

        return $this->turn->$carac;
    }


    public function put_malus($malus){


        $sql = 'UPDATE players SET malus = GREATEST(malus + ?, 0) WHERE id = ?';

        $db = new Db();

        $db->exe($sql, array($malus, $this->id));

        $this->refresh_data();
    }


    public function put_fat($fat){


        $sql = 'UPDATE players SET fatigue = GREATEST(fatigue + ?, 0) WHERE id = ?';

        $db = new Db();

        $db->exe($sql, array($fat, $this->id));

        $this->refresh_data();
    }


    public function change_god($god){


        $sql = 'UPDATE players SET godId = ?, pf = 0 WHERE id = ?';

        $db = new Db();

        $db->exe($sql, array($god->id, $this->id));

        $this->refresh_data();
    }


    public function get_gold(){


        $item = Item::get_item_by_name('or');

        return $item->get_n($this);
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
            SELECT COUNT(*) AS n
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
            ';

            $res = $db->exe($sql, array($_SESSION['mainPlayerId'], $_SESSION['mainPlayerId']));
        }
        else{


            $sql = 'SELECT COUNT(*) AS n FROM players_forum_missives WHERE player_id = ? AND viewed = 0';

            $res = $db->exe($sql, $this->id);
        }

        $row = $res->fetch_object();

        $n = $row->n;

        return $n;
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


    public function equip($item){


        $db = new Db();


        if(!isset($item->data)){

            $item->get_data();
        }


        if($item->row->name == 'poing'){

            return false;
        }


        $itemList = Item::get_equiped_list($this, $doNotRefresh=false);


        if(!empty($itemList[$item->id])){


            // item is cursed
            if($item->row->cursed){

                echo '<div id="data">Objet Maudit!</div>';
                return false;
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

            $return = 'unequip';
        }


        else{


            // item is NOT equiped : EQUIP

            if(!empty($this->{$item->data->emplacement}) && $this->{$item->data->emplacement}->id == $item->id){

                exit('unequip');
            }


            if(!Item::get_free_emplacement($this)){

                exit('error item limit');
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
                exit('cursed');
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
            if($munition = $this->get_munition($item)){

                if(!isset($itemList[$munition->id])){

                    $this->equip($munition);
                }
            }

            $return = 'equip';
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


    public function get_max_spells($spellsN){


        if(!isset($this->data)){

            $this->get_data();
        }

        $maxSpells = $this->data->rank + 1;

        return $maxSpells - $spellsN;
    }


    public function get_munition($item, $equiped=false){


        if(!isset($item->data->munitions)){

            return false;

        }

        foreach($item->data->munitions as $e){


            $munition = Item::get_item_by_name($e);

            if($munition->get_n($this, $bank=false, $equiped) > 0){


                return $munition;
            }
        }

        return false;
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

                $lootChance = floor($lootChance / 2);
            }

            // pnj loot chance
            if($this->id < 0){

                $lootChance = 100;
            }


            // perform loot
            if(rand(1,100) <= $lootChance){


                // rand loot n
                $lootN = floor($row->n * $lootChance / 100);

                if($lootN < 0) $lootN = 1;

                if($lootN > $row->n) $lootN = $row->n;

                if($lootN > 0){


                    // drop
                    $this->drop($loot, $lootN);

                    // populate lootList
                    $lootList[] = $loot->data->name .' x'. $lootN;
                }
            }
        }

        if(count($lootList)){


            $text = $this->data->name .' a perdu des objets: '. implode(', ', $lootList) .'.';

            Log::put($this, $this, $text, $type="loot");
        }


        // purge assists
        $values = array('target_id'=>$this->id);
        $db->delete('players_assists', $values);
    }


    public function distribute_xp() {


        $return = array();

        $target_id = $this->id;

        $timeLimit = time() - ONE_DAY;

        // Récupérer les détails de la cible
        $target_rank = $this->data->rank;
        $xp_to_distribute = $target_rank * 10;

        $return['xp_to_distribute'] = $xp_to_distribute;


        self::clean_players_assists();


        // Récupérer les assists des dernières 24 heures pour cette cible
        $stmt = db()->prepare("
            SELECT player_id, player_rank, damages, time
            FROM players_assists
            WHERE target_id = ? AND time > ?
            ORDER BY time DESC
        ");
        $stmt->bind_param('ii', $target_id, $timeLimit);
        $stmt->execute();
        $result = $stmt->get_result();

        $assists = $result->fetch_all(MYSQLI_ASSOC);

        $total_weight = 0;
        $weights = [];
        $xp_distribution = [];

        // Calculer les poids basés sur la différence de rang et les dommages
        foreach ($assists as $assist) {
            $weight = ($target_rank / max(1, $assist['player_rank'])) * $assist['damages'];
            $weights[$assist['player_id']] = $weight;
            $total_weight += $weight;
        }

        // Vérifier que le total des poids n'est pas zéro pour éviter la division par zéro
        if ($total_weight > 0) {
            // Répartir l'XP selon les poids calculés
            $total_distributed_xp = 0;
            foreach ($weights as $player_id => $weight) {
                $xp_share = floor(($weight / $total_weight) * $xp_to_distribute);
                $xp_distribution[$player_id] = $xp_share;
                $total_distributed_xp += $xp_share;
            }

            // Calculer l'XP restante
            $remaining_xp = $xp_to_distribute - $total_distributed_xp;

            // Ajouter l'XP restante au dernier joueur qui a infligé des dégâts
            if (!empty($assists)) {
                $last_assist_player_id = $assists[0]['player_id'];
                $xp_distribution[$last_assist_player_id] += $remaining_xp;
            }

            // Mettre à jour l'XP des joueurs
            foreach ($xp_distribution as $player_id => $xp_share) {

                $return[$player_id] = $xp_share;
            }
        } else {
            // Si total_weight est zéro, distribuer l'XP de manière égale à tous les participants
            if (!empty($assists)) {
                $equal_xp_share = floor($xp_to_distribute / count($assists));
                foreach ($assists as $assist) {

                    $return[$player_id] = $xp_to_distribute;
                }

                // Ajouter l'XP restante (due à l'arrondi) au dernier joueur qui a infligé des dégâts
                $remaining_xp = $xp_to_distribute - ($equal_xp_share * count($assists));

                $return['remaining_xp'] = $remaining_xp;

            }
        }

        return $return;
    }


    public function check_missive_permission($target){


        if(!isset($this->data)){

            $this->get_data();
        }

        if(!isset($target->data)){

            $target->get_data();
        }


        if(
            $this->id != $target->id
            &&
            (
                (
                    $target->data->faction == $this->data->faction
                    ||
                    $target->data->secretFaction == $this->data->secretFaction
                )
                ||
                $this->have_option('isAdmin')
                ||
                $target->have_option('isAdmin')
            )
        ){

            return true;
        }


        return false;
    }


    /*
     * STATIC FUNCTIONS
     */


    public static function put_player($name, $race, $pnj=false) : int{


        $db = new Db();


        $goCoords = (object) array(
            'x'=>0,
            'y'=>0,
            'z'=>0,
            'plan'=>'gaia'
        );

        $coordsId = View::get_coords_id($goCoords);


        $id = null;

        if($pnj){


            $id = $db->get_first_id('players') - 1;

            if(!$id){

                $id = -1;
            }
        }


        $raceJson = json()->decode('races', $race);


        $values = array(
            'id'=>$id,
            'name'=>$name,
            'race'=>$race,
            'avatar'=>'img/avatars/ame/'. $race .'.webp',
            'portrait'=>'img/portraits/ame/1.jpeg',
            'coords_id'=>$coordsId,
            'faction'=>$raceJson->faction,
            'nextTurnTime'=>time()
        );

        $db->insert('players', $values);

        $lastId = $db->get_last_id('players');

        $player = new Player($lastId);

        // first init data
        $player->get_data();


        // add tuto action
        $player->add_action('tuto/attaquer');


        Player::refresh_list();


        if($pnj){

            return $id;
        }


        // first id
        if($lastId == 1){

            $player->add_option('isAdmin');
        }


        Dialog::refresh_register_dialog();


        return $lastId;
    }

    public static function get_player_by_name($name){


        $db = new Db();

        $sql = '
        SELECT id FROM players WHERE name = ?
        ';

        $res = $db->exe($sql, $name);

        if(!$res->num_rows){

            return false;
        }

        $row = $res->fetch_object();

        return new Player($row->id);
    }

    public function get_data(){


        // first create dir
        if(!file_exists('datas/private/players/')){

            mkdir('datas/private/players/');
        }

        $playerJson = json()->decode('players', $this->id);


        // first player json
        if(!$playerJson){


            $player = new Player( $this->id);

            $player->get_row();


            // unset some unwanted var
            unset($player->row->psw);
            unset($player->row->mail);
            unset($player->row->ip);

            $path = 'datas/private/players/'. $player->id .'.json';
            $data = Json::encode($player->row);

            Json::write_json($path, $data);

            $playerJson = json()->decode('players',  $this->id);
        }

        $this->data = $playerJson;


        $pathInfo = pathinfo($this->data->portrait);

        $this->data->mini = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_mini.' . $pathInfo['extension'];

        $this->data->faction_img = 'img/factions/'. $this->data->faction .'.png';
        $this->data->faction_mini = 'img/factions/'. $this->data->faction .'_mini.png';


        return $playerJson;
    }


    public static function refresh_list(){


        $sql = 'SELECT id,name,race,xp,rank,pr,faction,secretFaction FROM players ORDER BY name';

        $db = new Db();

        $res = $db->exe($sql);

        $data = array();

        while($row = $res->fetch_object()){

            $data[] = $row;
        }

        $data = Json::encode($data);

        Json::write_json('datas/private/players/list.json', $data);
    }


    public static function refresh_views_at_z($z){


        $sql = 'SELECT players.id FROM players INNER JOIN coords ON coords.id = coords_id WHERE z = ?';

        $db = new Db();

        $res = $db->exe($sql, $z);

        while($row = $res->fetch_object()){


            @unlink('datas/private/players/'. $row->id .'.svg');
        }
    }


    public static function clean_players_assists(){


        $timeLimit = time() - ONE_DAY;

        // Optionnel: supprimer les assists
        $stmt = db()->prepare("DELETE FROM players_assists WHERE time < ?");
        $stmt->bind_param('i', $timeLimit);
        $stmt->execute();
    }
}
