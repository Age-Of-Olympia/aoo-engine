<?php

class Log{


    // STATIC

    public static function get(Player $player,$maxLogAge=ONE_DAY,$type=''){
        
        $return = array();
        $db = new Db();
        $timeLimit = time()-$maxLogAge;
        $typeCondition = '';

        switch ($type) {
            case 'mdj':
                $typeCondition = ' WHERE type = \'mdj\'';
                break;
            default:
                $typeCondition = ' WHERE type != \'mdj\'';
                break;
        }

        $sql = 'SELECT 
            final_logs.id,
            final_logs.player_id,
            final_logs.target_id,
            final_logs.text,
            final_logs.hiddenText,
            final_logs.type,
            final_logs.plan,
            final_logs.time,
            final_logs.coords_id,
            final_logs.last_player_movement_coords_id AS last_player_coords_id,
            c.plan AS movement_plan,
            c.x AS movement_x,
            c.y AS movement_y,
            c.z AS movement_z
        FROM (
            SELECT 
                logs.*,
                logs_player.coords_id AS last_player_movement_coords_id
            FROM (
                SELECT
                    pl.*,
                    MAX(CASE WHEN pl.player_id = ? AND pl.type = \'move\' THEN id END) OVER (
                        ORDER BY pl.time 
                        ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                    ) AS last_player_move_id
                FROM players_logs pl) logs
            LEFT JOIN players_logs logs_player ON logs.last_player_move_id = logs_player.id
            WHERE logs.time > ?
        ) AS final_logs
        LEFT JOIN coords c ON final_logs.last_player_movement_coords_id = c.id
        '.$typeCondition.'
        ORDER BY final_logs.time DESC';

        $res = $db->exe($sql, array($player->id, $timeLimit));

        while($row = $res->fetch_object()){

            // Temporary hide moves && other player action
            // if ($row->type == "move" || $row->type == "action_other_player") {
            //     continue;
            // }

            if ($row->type == "move" && $type == "light") {
                continue;
            }

            // If the event is about player, either as doer or as target, event is displayed

            if ($row->player_id == $player->id && $row->type != "travel") {
                $return[] = $row;
                continue;
            }

            if ($row->target_id == $player->id) {
                $return[] = $row;
                continue;
            }  

            // Get Percetion
            $caracsJson = json()->decode('players', $player->id .'.caracs');
            if(!$caracsJson){
                $player->get_caracs();
                $p = $player->caracs->p;
            }
            else{
                $p = $caracsJson->p;
            }

            // Create coord object to call get_coords_id_arround
            $last_player_coords = (object) array(
                'x'=>$row->movement_x,
                'y'=>$row->movement_y,
                'z'=>$row->movement_z,
                'plan'=>$row->movement_plan
            );

            $arrayCoords = View::get_coords_id_arround($last_player_coords, $p);

            if (in_array($row->coords_id, $arrayCoords)) {
                $return[] = $row;
            }
            
           
        }

        return $return;
    }

    public static function getAdmin($plan, $maxLogAge=THREE_DAYS){

        $return = array();
        $player = new Player($_SESSION['playerId']);
        if($player->have_option('isAdmin')) {
            $db = new Db();
            $timeLimit = time()-$maxLogAge;

            $sql = 'SELECT 
                players_logs.id,
                players_logs.player_id,
                players_logs.target_id,
                players_logs.text,
                players_logs.hiddenText,
                players_logs.type,
                players_logs.plan,
                players_logs.time,
                players_logs.coords_id
            FROM players_logs
            WHERE players_logs.time > ? AND plan = ?   
            ORDER BY players_logs.time DESC';

            $res = $db->exe($sql, array($timeLimit, $plan));

            while($row = $res->fetch_object()){
                $return[] = $row;
                continue;
            }
        }

        return $return;
    }


    public static function put(Player $player, $target, $text, $type='', $hiddenText=''){


        if(!isset($player->coords)){

            $player->get_coords();
        }

        $plan = $player->coords->plan;

        // hide log in incognitoMode
        if($player->have_option('incognitoMode')){
            $text = "Plan d'origine : ".$plan." - ".$text;
            $plan = "birdland"; // show logs in birdlands for posterity
        }

        $targetId = (is_numeric($target)) ? $target : $target->id;

        $values = array(
            'player_id'=>$player->id,
            'target_id'=>$targetId,
            'text'=>$text,
            'plan'=>$plan,
            'time'=>time(),
            'type'=>$type,
            'coords_id'=>View::get_coords_id($player->coords)
        );

        $db = new Db();

        $res = $db->insert('players_logs', $values);

        if ($hiddenText != '') {
            $sql = 'UPDATE players_logs SET hiddenText = ? WHERE type = ? AND player_id = ? ORDER BY time DESC LIMIT 1';
            $db->exe($sql, array($hiddenText, $type, $player->id));
        }
        
    }
}
