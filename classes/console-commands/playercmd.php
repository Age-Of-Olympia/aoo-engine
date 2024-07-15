<?php

class PlayerCmd extends Command
{
    public function __construct() {
        parent::__construct("player",[new Argument('action',false), new Argument('mat',false), new Argument('option1',true), new Argument('option2',true)]);
        parent::setDescription(<<<EOT
Manipule la table "players".
Exemple:
> player create Orcrist olympien
> player create Ocyrhoée elfe,pnj
> player edit Finn race,lutin
> player edit 1 name,Léo
> player additem Orcrist or 100
> player additem 1 1 -100
> player addbank Orcrist pierre 100
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {

        $action = $argumentValues[0];


        if($action == 'create'){


            $name = $argumentValues[1];


            $optList = explode(',', $argumentValues[2]);


            $pnj = false;


            if(count($optList) == 1){

                list($race) = $optList;
            }
            elseif(count($optList) == 2){

                list($race, $pnj) = $optList;
            }
            else{

                return 'invalid option ('. $argumentValues[2] .'). Must be: "race" or "race,pnj", ie. "elfe" or "lutin,pnj"';
            }


            if(!$raceJson = json()->decode('races', $race)){

                return '<font color="red">invalid race ('. $race .')</font>';
            }


            $lastId = Player::put_player($name, $race, $pnj);


            $pnjTxt = ($pnj) ? 'pnj=true' : 'pnj=false';


            return 'player '. $name .' created ('. $raceJson->name .', '. $pnjTxt .') <a href="#" OnClick="document.getElementById(\'input-line\').value = \'session open '. $lastId .'\'; document.getElementById(\'input-line\').focus()">mat: '. $lastId .'</a>';
        }

        else{


            $player=parent::getPlayer($argumentValues[1]);

            $player->get_data();


            if($action == 'edit'){


                if(!isset($argumentValues[2])){

                    return '<font color="red">invalid argument option1 ('. $argumentValues[2] .').<br />
                    Usage: player edit [mat] [field] [value] ie player edit Orcrist name "Orcrist le Vénérable"</font>';
                }

                if(!isset($argumentValues[3])){

                    return '<font color="red">invalid argument option2 ('. $argumentValues[3] .').<br />
                    Usage: player edit [mat] [field] [value] ie player edit Orcrist name "Orcrist le Vénérable"</font>';
                }


                $field = $argumentValues[2];

                $value = $argumentValues[3];


                if(in_array($field, array('id','coords_id','mail','psw','ip'))){

                    return '<font color="orange">field "'. $field .'" is protected</font>';
                }


                if(!isset($player->data->$field)){

                    return '<font color="red">invalid field option ('. $field .') does not exists.</font>';
                }

                if(is_numeric($player->data->$field) && !is_numeric($value)){

                    return '<font color="red">invalid value option ('. $value .') this field require numeric value.</font>';
                }


                $sql = '
                UPDATE players
                SET
                `'. $field .'` = ?
                WHERE
                id = ?
                ';

                $db = new Db();

                $sql = $db->exe($sql, array($value, $player->id));


                $player->refresh_data();


                return 'player '. $player->data->name .': field "'. $field .'" changed to value "'. $value .'"';
            }

            if($action == 'purge'){


                if($argumentValues[2] == 'view'){

                    $files = glob('datas/private/players/'. $player->id .'.svg');
                }
                elseif($argumentValues[2] == 'all'){

                    $files = glob('datas/private/players/'. $player->id .'*');
                }
                elseif($argumentValues[2] == 'allplayers'){

                    $files = glob('datas/private/players/*');
                }

                ob_start();

                foreach($files as $file){

                    @unlink($file);
                    echo $file;
                }

                $return = ob_get_clean();

                return 'player '. $player->data->name .': '. $argumentValues[2] .' cache purged '. $return;
            }


            if($action == 'additem' || $action == 'addbank'){


                if(!isset($argumentValues[2])){

                    return '<font color="red">error missing option1 [item id or name]. usage: player additem [mat] [existing item id or name] [number]</font>';
                }

                if(!isset($argumentValues[3])){

                    return '<font color="red">error missing option2 [number]. usage: player additem [mat] [existing item id or name] [number]</font>';
                }

                ob_start();

                if(is_numeric($argumentValues[2])){

                    $item = new Item($argumentValues[2]);
                }
                else{

                    $item = Item::get_item_by_name($argumentValues[2]);
                }

                $item->get_data();

                $bank = ($action == 'addbank') ? true : false;

                $place = ($action == 'addbank') ? 'bank' : 'inventory';

                $item->add_item($player, $argumentValues[3], $bank);

                $return = ob_get_clean();

                return 'player '. $player->data->name .': '. $item->data->name .' x'. $argumentValues[3] .' added to '. $place;
            }
        }

        if($action == 'addxp'){


            if(!isset($argumentValues[2])){

                return '<font color="red">error missing option1 [xp]. usage: player addxp [mat] [xp]</font>';
            }

            $player->put_xp($argumentValues[2]);

            return $argumentValues[2] .'Xp et Pi ajoutés à '. $player->data->name;

        }


        if($action == 'addlog'){


            if(!isset($argumentValues[2])){

                return '<font color="red">error missing option1 [target id or name]. usage: player addlog [mat]  [target id or name] [text]</font>';
            }

            if(!isset($argumentValues[3])){

                return '<font color="red">error missing option2 [text]. usage: player addlog [mat]  [target id or name] [text]</font>';
            }

            if(is_numeric($argumentValues[2])){

                $target = new Player($argumentValues[2]);
            }
            else{

                $target = Player::get_player_by_name($argumentValues[2]);
            }


            $target->get_data();

            Log::put($player, $target, $argumentValues[3], $type="console");

            return $player->data->name .' to '. $target->data->name .' log added: "'. $argumentValues[3] .'"';
        }

        if($action == 'deletelastlog'){


            $sql = 'DELETE FROM players_logs WHERE player_id = ? ORDER BY time DESC LIMIT 1';

            $db = new Db();

            $db->exe($sql, $player->id);

            return $player->data->name .' last log deleted';
        }

        return '<font color="orange">no changes detected</font>';
    }
}
