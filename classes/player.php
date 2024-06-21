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


        $this->row = $row;
    }


    public function get_caracs(){


        if(!isset($this->data)){

            $this->get_data();
        }


        $raceJson = json()->decode('races', $this->data->race);

        $this->raceData = $raceJson;

        $this->get_upgrades();

        foreach(CARACS as $k=>$e){

            $this->caracs->$k = $this->raceData->$k + $this->upgrades->$k;
        }
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


    public function add($table, $name){


        $values = array(
            'player_id'=>$this->id,
            'name'=>$name
        );


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
    public function add_action($name){ $this->add('actions', $name); }
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

                echo ucfirst($name) .' annule '. ucfirst(ELE_CONTROLS[$name]) .'.';
            }

            if(!empty(ELE_IS_CONTROLED[$name])){


                if($this->have_effect(ELE_IS_CONTROLED[$name])){

                    $this->end_effect(ELE_IS_CONTROLED[$name]);
                    $this->end_effect($name);

                    echo ucfirst(ELE_IS_CONTROLED[$name]) .' et '. ucfirst($name) .' s\'annulent.';
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


        $sql = 'UPDATE players SET coords_id = ? WHERE id = ?';

        $db->exe($sql, array($coordsId, $this->id));

        $this->refresh_data();


        // add elements
        $sql = 'SELECT name FROM map_elements WHERE coords_id = ?';

        $res = $db->exe($sql, $coordsId);

        while($row = $res->fetch_object()){


            if(FISHING || $this->have_item('canne_a_peche')){


                echo '
                <script>
                    alert("Ça mord!");
                    document.location = "fish.php";
                </script>
                ';
            }


            $this->add_effect($row->name, ONE_DAY);
        }

        // delete svg cache
        $sql = '
        SELECT p.id AS id
        FROM
        players AS p
        INNER JOIN
        coords AS c
        ON
        p.coords_id = c.id
        WHERE
        c.z = ?
        AND
        c.plan = ?
        ';

        $res = $db->exe($sql, array($goCoords->z, $goCoords->plan));

        while($row = $res->fetch_object()){


            @unlink('datas/private/players/'. $row->id .'.svg');
        }


        // delete empty coords (except #1)
        $sql = '
        DELETE FROM
        coords
        WHERE
        id NOT IN (
            SELECT coords_id FROM players
            UNION
            SELECT coords_id FROM map_elements
            UNION
            SELECT coords_id FROM map_tiles
            UNION
            SELECT coords_id FROM map_triggers
            UNION
            SELECT coords_id FROM map_walls
            UNION
            SELECT coords_id FROM map_dialogs
            UNION
            SELECT coords_id FROM map_plants
            )
        AND
        id != 1
        ';

        $db->exe($sql);
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


    public function refresh_view(){

        @unlink('datas/private/players/'. $_SESSION['playerId'] .'.svg');
    }

    public function refresh_data(){

        @unlink('datas/private/players/'. $this->id .'.json');
    }


    public function put_pf($pf){


        $this->row->pf += $pf;

        $sql = 'UPDATE players SET pf = pf + ? WHERE id = ?';

        $db = new Db();

        $db->exe($sql, array($pf, $this->id));

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


        if($n > $item->get_n($this)){

            exit('error n');
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


        $values = array(
            'id'=>$id,
            'name'=>$name,
            'race'=>$race,
            'avatar'=>'img/avatars/ame/'. $race .'.webp',
            'portrait'=>'img/portraits/'. $race .'/1.jpeg',
            'coords_id'=>$coordsId
        );

        $db->insert('players', $values);

        $lastId = $db->get_last_id('players');

        $player = new Player($lastId);

        // first init data
        $player->get_data();


        if($pnj){

            return $id;
        }

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

            $path = 'datas/private/players/'. $player->id .'.json';
            $data = Json::encode($player->row);

            Json::write_json($path, $data);

            $playerJson = json()->decode('players',  $this->id);
        }

        $this->data = $playerJson;

        return $playerJson;
    }


    public static function refresh_list(){


        $sql = 'SELECT id,name,race FROM players ORDER BY name';

        $db = new Db();

        $res = $db->exe($sql);

        $data = array();

        while($row = $res->fetch_object()){

            $data[] = $row;
        }

        $data = Json::encode($data);

        Json::write_json('datas/private/players/list.json', $data);
    }
}
