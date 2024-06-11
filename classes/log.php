<?php

class Log{


    // STATIC

    public static function get($plan){


        $return = array();

        $db = new Db();

        $sql = 'SELECT * FROM players_logs WHERE plan = ? ORDER BY time DESC';

        $res = $db->exe($sql, $plan);

        while($row = $res->fetch_object()){

            $return[] = $row;
        }

        return $return;
    }


    public static function put($player, $target, $text, $type=''){


        if(!isset($player->coords)){

            $player->get_coords();
        }


        $targetId = (is_numeric($target)) ? $target : $target->id;

        $values = array(
            'player_id'=>$player->id,
            'target_id'=>$targetId,
            'text'=>$text,
            'plan'=>$player->coords->plan,
            'time'=>time(),
            'type'=>$type
        );

        $db = new Db();

        $db->insert('players_logs', $values);
    }
}
