<?php

class Log{


    // STATIC

    // Should be mouved in view class (in Log now to have only one file to update to fix)
    public static function compute_unique_coord(&$inputCoord, $key, array $coordCompletion)
    {
        $inputCoord = str_replace(",","_",$inputCoord)."_".$coordCompletion[0]."_".$coordCompletion[1];
    }

    public static function get(Player $player,$maxLogAge=ONE_DAY,$type=''){
        
        $return = array();
        $db = new Db();
        $typeCondition = '';

        switch ($type) {
            case 'mdj':
                $typeCondition = ' WHERE final_logs.type = \'mdj\'';
                $maxLogAge = THREE_DAYS;
                break;
            default:
                $typeCondition = ' WHERE final_logs.type != \'mdj\'';
                break;
        }

        $timeLimit = time()-$maxLogAge;

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
            final_logs.coords_computed,
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
                SELECT pl.*,
                    (
                        SELECT MAX(pl2.id)
                        FROM players_logs pl2
                        WHERE pl2.player_id = ?
                        AND pl2.time <= pl.time
                        ORDER BY pl2.time DESC
                        LIMIT 1
                    ) AS last_player_move_id
                FROM players_logs pl) logs
            LEFT JOIN players_logs logs_player ON logs.last_player_move_id = logs_player.id
            
        ) AS final_logs
        LEFT JOIN coords c ON final_logs.last_player_movement_coords_id = c.id
        '.$typeCondition.'
        AND final_logs.time > ?
        ORDER BY final_logs.time DESC';

        $res = $db->exe($sql, array($player->id, $timeLimit));

        while($row = $res->fetch_object()){

            if ($row->type == "move" && $type == "light") {
                continue;
            }

            if ($row->plan == "birdland") {
                continue;
            }

            // If the event is about player, either as doer or as target, event is displayed
            // Two lines of travel are stored, one in the departure plan and ont in the arrival plan,
            // We hide one of the two !
            if ($row->player_id == $player->id && $row->type != "travel") {
                $return[] = $row;
                continue;
            }

            if ($row->target_id == $player->id) {
                $return[] = $row;
                continue;
            }  

            // Get Perception
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
            
            // Computing coords
            $arrayCoordsId = View::get_coords_arround($last_player_coords, $p);
            array_walk($arrayCoordsId, array(Log::class, 'compute_unique_coord'), [$last_player_coords->z, $last_player_coords->plan]);

            if (in_array($row->coords_computed, $arrayCoordsId)) {
                $return[] = $row;
            }            
           
        }

        return $return;
    }

    public static function getAllPlanEvents($plan, $maxLogAge=THREE_DAYS){

        $return = array();
        $player = new Player($_SESSION['playerId']);

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

        $coordToLog = $player->coords->x."_".$player->coords->y."_".$player->coords->z."_".$player->coords->plan;

        $values = array(
            'player_id'=>$player->id,
            'target_id'=>$targetId,
            'text'=>$text,
            'plan'=>$plan,
            'time'=>time(),
            'type'=>$type,
            'coords_id'=>View::get_coords_id($player->coords),
            'coords_computed'=>$coordToLog
        );

        $db = new Db();

        $res = $db->insert('players_logs', $values);

        if ($hiddenText != '') {
            $sql = 'UPDATE players_logs SET hiddenText = ? WHERE type = ? AND player_id = ? ORDER BY time DESC LIMIT 1';
            $db->exe($sql, array($hiddenText, $type, $player->id));
        }
        
    }
}
