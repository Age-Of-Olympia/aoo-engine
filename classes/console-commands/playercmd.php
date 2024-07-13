<?php

class PlayerCmd extends Command
{
    public function __construct() {
        parent::__construct("player",[new Argument('action',false), new Argument('mat',false), new Argument('options',true)]);
        parent::setDescription(<<<EOT
Manipule la table "players".
Exemple:
> player create Orcrist olympien
> player create Ocyrhoée elfe,pnj
> player edit Finn race,lutin
> player edit 1 name,Léo
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


            $optList = explode(',', $argumentValues[2]);


            if($action == 'edit'){


                if(count($optList) != 2){

                    return '<font color="red">invalid options ('. $argumentValues[2] .').<br />
                    Must be: "field,value" ie. "race,lutin"</font>';
                }


                list($field, $value) = $optList;


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
        }

        return '<font color="orange">no changes detected</font>';
    }
}
