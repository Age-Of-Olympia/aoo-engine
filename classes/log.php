<?php

class Log{


    // STATIC

    // Should be mouved in view class (in Log now to have only one file to update to fix)
    public static function compute_unique_coord(&$inputCoord, $key, array $coordCompletion)
    {
        $inputCoord = str_replace(",","_",$inputCoord)."_".$coordCompletion[0]."_".$coordCompletion[1];
    }

    public static function get(Player $player,$maxLogAge=THREE_DAYS,$type=''){
        
        $return = array();
        $db = new Db();
        $typeCondition = '';

        switch ($type) {
            case 'mdj':
                $typeCondition = ' WHERE final_logs.type = \'mdj\'';
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
                        AND pl2.type = \'move\'
                        ORDER BY pl2.time DESC
                        LIMIT 1
                    ) AS last_player_move_id
                FROM players_logs pl) logs
            LEFT JOIN players_logs logs_player ON logs.last_player_move_id = logs_player.id
            
        ) AS final_logs
        LEFT JOIN coords c ON final_logs.last_player_movement_coords_id = c.id
        '.$typeCondition.'
        AND final_logs.time > ?
        ORDER BY final_logs.id DESC';

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
                if ($row->type != "hidden_action_other_player")
                {
                    $return[] = $row;
                }
                continue;
            }

            if ($row->target_id == $player->id) {
                if ($row->type != "hidden_action")
                {
                    $return[] = $row;
                }
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

            $planJson = json()->decode('plans', $row->plan);
            // For PNJs, check if event is in their current plan
            if ($player->id <= 0) {
                if ($row->plan != $player->coords->plan) {
                    continue;
                }
            }
            // Only check player_visibility for normal players
            else if (!$planJson || (isset($planJson->player_visibility) && $planJson->player_visibility === false)) {
                continue;
            }

            if (in_array($row->coords_computed, $arrayCoordsId)) {
                $return[] = $row;
                continue;
            }

            $raceJson = json()->decode('races', $player->data->race);
            // if the player is in his home plan at the moment of the event + it is a travel
            if ($raceJson->plan == $row->plan && $row->movement_plan == $row->plan && $row->type == "travel") {
                // we get the plan pnj
                if (isset($planJson->pnj)) {
                    $pnj = new Player($planJson->pnj);
                    $pnj->get_coords();
                    // if the pnj is in the plan at the moment (should be at the time of event but it would be one more sql request)
                    if (isset($pnj->coords->plan) && ($pnj->coords->plan == $row->plan)) {
                        $return[] = $row;
                        continue;
                    }
                }
            }
        }

        $return = Log::filterRows($return, $player->id);
        return $return;
    }

/**
 * Filters an array of row objects by identifying pairs of rows that meet the specified conditions.
 * 
 * Conditions for identifying a pair:
 * - Two consecutive rows have the same timestamp.
 * - They have the same action type.
 * - The player of the first row is the target of the second row OR the target of the first row is the player of the second row.
 * 
 * For pairs that match these conditions: keep only one row amongst the two.
 * 
 * If a pair is not matched or identified, the row is kept as is.
 * 
 * @param array $rows An array of objects. Each object is expected to have 'time', 'player', 'target', and 'type' properties.
 * @param int $playerId The identifier of the player to prioritize in pairs.
 * @return array The filtered array of rows.
 */
private static function filterRows(array $rows, int $playerId): array {
    $filtered = [];

    $count = count($rows);
    for ($i = 0; $i < $count; $i++) {
        // Check if we can form a pair with the next row
        if ($i < $count - 1) {
            $current = $rows[$i];
            $next = $rows[$i + 1];

            $isPair = (
                ($current->time === $next->time)
                &&
                ((($current->type === "action" && $next->type === "action_other_player") ||
                ($current->type === "action_other_player" && $next->type === "action")) 
                ||
                (($current->type === "hidden_action" && $next->type === "hidden_action_other_player") ||
                ($current->type === "hidden_action_other_player" && $next->type === "hidden_action")) 
                ||
                ($current->type === "travel" && $next->type === "travel")
                ||
                ($current->type === "kill" && $next->type === "kill"))
                &&
                (
                    $current->player_id === $next->target_id ||
                    $current->target_id === $next->player_id
                )
            );

            if ($isPair) {
                if ($current->player_id === $playerId) {
                    if ($current->type != "travel") {
                        $filtered[] = $current;
                    }
                }
                if ($next->player_id === $playerId) {
                    $filtered[] = $next;
                }
                if (($current->player_id != $playerId) && ($next->player_id != $playerId)) {
                    $filtered[] = $next;
                }

                $i++; // Move past the pair
                continue;
            }
        }

        // If not part of a filtered pair, just keep the row
        $filtered[] = $rows[$i];
    }

    return $filtered;
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


    public static function put(Player $player, $target, $text, $type='', $hiddenText='', $logTime=''){


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

        if (str_starts_with($type, "hidden_")) {
            $coordsId = NULL;
            $coordToLog = NULL;
        } else {
            $coordsId = View::get_coords_id($player->coords);
            $coordToLog = $player->coords->x."_".$player->coords->y."_".$player->coords->z."_".$player->coords->plan;
        }

        if ($logTime != '') {
            $time = $logTime;
        } else {
            $time = time();
        }

        $values = array(
            'player_id'=>$player->id,
            'target_id'=>$targetId,
            'text'=>$text,
            'plan'=>$plan,
            'time'=>$time,
            'type'=>$type,
            'coords_id'=>$coordsId,
            'coords_computed'=>$coordToLog,
            'hiddenText'=>$hiddenText
        );

        $db = new Db();

        $res = $db->insert('players_logs', $values);
    }
}
