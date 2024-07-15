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
        }

        return '<font color="orange">no changes detected</font>';
    }
}
