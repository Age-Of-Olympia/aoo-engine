<?php


class Quest{


    public $playerId;       // player id
    public $playerQuests;   // player quests list (loaded only once)


    function __construct($playerId){


        if(!is_numeric($playerId)){

            $playerId = $playerId->id;
        }


        $this->playerId = $playerId;
    }


    public function put_player_quest($questName){


        if(!$quest = self::get_quest($questName)){

            return false;
        }

        $db = new Db();

        $values = array(
            'player_id'=>$this->playerId,
            'quest_id'=>$quest->id,
            'startTime'=>time()
        );

        $db->insert('players_quests', $values);

        return true;
    }


    public function get_player_quests(){


        if(!empty($this->playerQuests)){

            return $this->playerQuests;
        }


        $db = new Db();

        $sql = '
        SELECT *
        FROM
        players_quests AS p
        INNER JOIN
        quests AS q
        ON
        q.id = p.quest_id
        WHERE
        player_id = ?
        ORDER BY
        startTime';

        $res = $db->exe($sql, $this->playerId);

        $return = array();

        while($row = $res->fetch_object()){


            $return[$row->name] = $row;
        }

        $this->playerQuests = $return;

        return $return;
    }


    public function check_player_quest($questName){


        $playerQuests = $this->get_player_quests();

        if(!isset($playerQuests[$questName])){

            return false;
        }

        return $playerQuests[$questName];
    }


    public function get_player_quest($questName){


        $playerQuests = $this->get_player_quests();

        return $playerQuests[$questName];
    }


    public function put_step($questName, $stepName) : bool{


        if(!$playerQuest = $this->get_player_quest($questName)){

            return false;
        }

        $db = new Db();

        $values = array(
            'player_id'=>$this->playerId,
            'quest_id'=>$playerQuest->quest_id,
            'name'=>$stepName
        );

        $db->insert('players_quests_steps', $values);

        return true;
    }


    public function delete_step($questName, $stepName) : bool{


        if(!$playerQuest = $this->get_player_quest($questName)){

            return false;
        }

        $db = new Db();

        $values = array(
            'player_id'=>$this->playerId,
            'quest_id'=>$playerQuest->quest_id,
            'name'=>$stepName
        );

        $db->delete('players_quests_steps', $values);

        return true;
    }


    public function get_steps($questName){


        if(!$playerQuest = $this->get_player_quest($questName)){

            return false;
        }


        $return = array();

        $db = new Db();

        $sql = 'SELECT * FROM players_quests_steps WHERE player_id = ? AND quest_id = ?';

        $res = $db->exe($sql, array($this->playerId, $playerQuest->quest_id));

        while($row = $res->fetch_object()){


            $return[$row->name] = $row;
        }

        return $return;
    }


    public function get_step($questName, $stepName){


        if(!$playerQuest = $this->get_player_quest($questName)){

            return false;
        }

        $return = array();

        $db = new Db();

        $sql = '
        SELECT * FROM
        players_quests_steps
        WHERE
        player_id = ?
        AND
        quest_id = ?
        AND
        name = ?
        ';

        $res = $db->exe($sql, array($this->playerId, $playerQuest->quest_id, $stepName));

        if(!$res->num_rows){

            return false;
        }

        return $res->fetch_object();
    }


    public function edit_step($questName, $stepName, $field, $value) : bool{


        if(!$playerQuest = $this->get_player_quest($questName)){

            return false;
        }

        if(!isset($playerQuest->$field)){

            return false;
        }

        $db = new Db();

        $sql = '
        UPDATE
        players_quests_steps
        SET `'. $field .'` = ?
        WHERE
        player_id = ?
        AND
        quest_id = ?
        AND
        name = ?
        ';

        $db->exe($sql, array($value, $this->playerId, $playerQuest->quest_id, $stepName));

        return true;
    }


    public function step_toggle_status($questName, $stepName) : bool{


        if(!$step = $this->get_step($questName, $stepName)){

            return false;
        }

        $value = ($step->status == 'pending') ? 'complete' : 'pending';

        if($value == 'complete'){


            $this->step_end($questName, $stepName);

            $this->complete($questName);
        }
        else{

            $this->edit_step($questName, $stepName, field:'endTime', value:0);
        }

        return $this->edit_step($questName, $stepName, field:'status', value:$value);
    }


    public function complete($questName){


        if(!$playerQuest = $this->get_player_quest($questName)){

            return false;
        }

        $db = new Db();

        $sql = '
        SELECT
        COUNT(*) AS n
        FROM
        players_quests_steps
        WHERE
        player_id = ?
        AND
        quest_id = ?
        AND
        status != "complete"
        ';

        $res = $db->exe($sql, array($this->playerId, $playerQuest->id));

        $row = $res->fetch_object();

        if(!$row->n){


            $sql = '
            UPDATE
            players_quests
            SET
            status = "complete",
            endTime = '. time() .'
            WHERE
            player_id = ?
            AND
            quest_id = ?
            ';

            $db->exe($sql, array($this->playerId, $playerQuest->id));

            return true;
        }

        return false;
    }


    public function step_end($questName, $stepName) : bool{


        if(!$step = $this->get_step($questName, $stepName)){

            return false;
        }

        if($step->endTime){

            return false;
        }

        $value = time();

        return $this->edit_step($questName, $stepName, field:'endTime', value:$value);
    }


    public function reset_quest($questName){


        if(!$playerQuest = $this->get_player_quest($questName)){

            return false;
        }

        $db = new Db();

        $sql = '
        UPDATE
        players_quests_steps
        SET `status` = "pending"
        WHERE
        player_id = ?
        AND
        quest_id = ?
        ';

        $db->exe($sql, array($this->playerId, $playerQuest->quest_id));

        $sql = '
        UPDATE
        players_quests
        SET
        `status` = "pending",
        `endTime` = 0,
        `startTime` = '. time() .'
        WHERE
        player_id = ?
        AND
        quest_id = ?
        ';

        $db->exe($sql, array($this->playerId, $playerQuest->quest_id));

        return true;
    }


    public function permanent($questName){


        if(!$playerQuest = $this->get_player_quest($questName)){

            return false;
        }

        $db = new Db();

        $sql = '
        UPDATE
        players_quests_steps
        SET `status` = "permanent"
        WHERE
        player_id = ?
        AND
        quest_id = ?
        ';

        $db->exe($sql, array($this->playerId, $playerQuest->quest_id));

        return true;
    }

    /*
     * static
     */


    public static function put_quest($name, $text='') : bool{


        if(self::get_quest($name)){

            return false;
        }

        $values = array(
            'name'=>$name,
            'text'=>$text
        );

        $db = new Db();

        $db->insert('quests', $values);

        return true;
    }

    public static function delete_quest($name) : bool{


        if(!$quest = self::get_quest($name)){

            return false;
        }

        $values = array(
            'id'=>$quest->id
        );

        $db = new Db();

        $db->delete('quests', $values);

        return true;
    }

    public static function get_quests() : array{


        $return = array();

        $db = new Db();

        $sql = 'SELECT * FROM quests';

        $res = $db->exe($sql);

        while($row = $res->fetch_object()){


            $row->img = self::get_img($row->name);

            $return[$row->name] = $row;
        }

        return $return;
    }

    public static function get_quest($name){


        $return = array();

        $db = new Db();


        if(!is_numeric($name)){


            $sql = 'SELECT * FROM quests WHERE name = ?';
        }
        else{


            $sql = 'SELECT * FROM quests WHERE id = ?';
        }

        $res = $db->exe($sql, $name);

        if(!$res->num_rows){


            return false;
        }

        return $res->fetch_object();
    }

    public static function edit_quest($questName, $field, $value) : bool{


        if(!$quest = self::get_quest($questName)){

            return false;
        }

        if(!isset($quest->$field)){

            return false;
        }

        $db = new Db();

        $sql = '
        UPDATE
        quests
        SET `'. $field .'` = ?
        WHERE
        id = ?
        ';

        $db->exe($sql, array($value, $quest->id));

        return true;
    }

    public static function get_img($name){


        $path = 'img/quests/'. $name .'.webp';

        return (file_exists($path)) ? $path : 'img/quests/default.webp';
    }
}
