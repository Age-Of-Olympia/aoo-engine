<?php
use Classes\Command;
use Classes\Argument;
use Classes\Quest;

class QuestCmd extends Command
{
    public function __construct() {
        parent::__construct("quest",[new Argument('action',false), new Argument('mat',true), new Argument('option1',true), new Argument('option2',true)]);
        parent::setDescription(<<<EOT
Manipule la table "quests".
Exemple:
> quest list
> quest create "Une Quête"
> quest edit "Une Quête" text "La quête de ta vie"
> quest infos "Une Quête"
> quest delete "Une Quête"
> quest player Orcrist
> quest player Orcrist start "Une Quête"
> quest player Orcrist infos "Une Quête"
> quest player Orcrist newstep "Une Quête" "Une première étape"
> quest player Orcrist reset "Une Quête"
> quest player Orcrist permanent "Une Quête"
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {

        ob_start();

        $action = $argumentValues[0];


        if($action == 'list'){


            $quests = Quest::get_quests();

            ob_start();

            foreach($quests as $row){


                echo '#'. $row->id .' <a href="#" OnClick="document.getElementById(\'input-line\').value = \'quest infos '. $row->id .'\'; document.getElementById(\'input-line\').focus()">'. $row->name .'</a><br />';
            }

            return ob_get_clean();
        }

        elseif($action == 'infos'){


            if(!isset($argumentValues[1])){

                return '<font color="red">error: missing argument "questId". ie: "quest infos 1" or: quest infos "La Traque de Neach"</font>';
            }

            if(!$quest = Quest::get_quest($argumentValues[1])){

                return '<font color="orange">quest: "'. $argumentValues[1] .'" not found</font>';
            }

            ob_start();

            echo 'quest found in table quests:<br />';

            printr($quest);

            echo '<a href="#" OnClick="document.getElementById(\'input-line\').value = \'quest edit '. $quest->id .' name '. htmlentities('"'. $quest->name .'"') .'\'; document.getElementById(\'input-line\').focus()">edit</a> ';

            echo '<a href="#" OnClick="document.getElementById(\'input-line\').value = \'quest delete '. $quest->id .'\'; document.getElementById(\'input-line\').focus()">delete</a> ';

            return ob_get_clean();
        }

        elseif($action == 'edit'){


            if(!isset($argumentValues[1])){

                return '<font color="red">error: missing argument "questId". ie: "quest edit 1" or: quest edit "La Traque de Neach"</font>';
            }

            if(!$quest = Quest::get_quest($argumentValues[1])){

                return '<font color="orange">quest: "'. $argumentValues[1] .'" not found</font>';
            }


            if(!isset($argumentValues[2])){

                return '<font color="red">error: missing argument "field". usage: "quest edit [questId or questName] [field] [value]"</font>';
            }

            $field = $argumentValues[2];

            if(in_array($field, array('id'))){

                return '<font color="orange">aborting: field "'. $field .'" is protected</font>';
            }


            if(!isset($argumentValues[3])){

                return '<font color="red">error: missing argument "value". usage: "quest edit [questId or questName] [field] [value]"</font>';
            }

            $value = $argumentValues[3];


            if(!isset($quest->$field)){

                return '<font color="red">error: field "'. $field .'" does not exists</font>';
            }

            if(!Quest::edit_quest($quest->id, $field, $value)){

                return '<font color="orange">something went wrong with Quest::edit_quest()</font>';
            }

            return 'success: '. $quest->$field .' => '. $value .'';
        }

        elseif($action == 'create'){


            if(!isset($argumentValues[1])){

                return '<font color="red">error: missing argument "name". ie: quest create Hello? or ie: quest create "Hello j\'égoutte?"</font>';
            }


            $name = $argumentValues[1];


            if($quest = Quest::get_quest($name)){

                return '<font color="orange">aborting: quest "'. $name .'" already exists (#'. $quest->id .')</font>';
            }


            if(!Quest::put_quest($name)){

                return '<font color="orange">something went wrong with Quest::put_quest()</font>';
            }


            $newQuest = Quest::get_quest($name);


            return 'success: quest <a href="#" OnClick="document.getElementById(\'input-line\').value = \'quest edit '. $newQuest->id .'\'; document.getElementById(\'input-line\').focus()">'. $name .'</a> successfully created (#'. $newQuest->id .')';
        }

        elseif($action == 'delete'){


            if(!isset($argumentValues[1])){

                return '<font color="red">error: missing argument "name". ie: quest delete Hello? or ie: quest delete "Hello j\'égoutte?"</font>';
            }


            $name = $argumentValues[1];


            if(!$quest = Quest::get_quest($name)){

                return '<font color="orange">aborting: quest "'. $name .'" does not exists</font>';
            }


            if(!Quest::delete_quest($name)){

                return '<font color="orange">something went wrong with Quest::delete_quest()</font>';
            }

            return 'success: '. $name .' successfully deleted';
        }

        elseif($action == 'player'){


            $player=parent::getPlayer($argumentValues[1]);

            $player->get_data();

            $quest = new Quest($player);


            if(!empty($argumentValues[2])){


                if($argumentValues[2] == 'start'){


                    if(!isset($argumentValues[3])){

                        return '<font color="red">error: missing argument "questId or questName". ie: quest player Orcrist start "La Traque de Neach"</font>';
                    }

                    ob_start();

                    if(!$infoQuest = Quest::get_quest($argumentValues[3])){

                        return '<font color="orange">aborting: quest "'. $argumentValues[3] .'" does not exists.</font>';
                    }

                    $quest->put_player_quest($infoQuest->name);

                    return $player->data->name .' quest "'. $infoQuest->name .'" successfully started';
                }


                else if($argumentValues[2] == 'infos'){


                    if(!isset($argumentValues[3])){

                        return '<font color="red">error: missing argument "questId or questName". ie: quest player Orcrist infos "La Traque de Neach"</font>';
                    }

                    ob_start();

                    if(!$infoQuest = Quest::get_quest($argumentValues[3])){

                        return '<font color="orange">aborting: quest "'. $argumentValues[3] .'" does not exists.</font>';
                    }

                    $playerQuest = $quest->get_player_quest($infoQuest->name);

                    echo 'quest: '. $playerQuest->name .'<br />';

                    echo 'status: '. $playerQuest->status .'<br />';

                    echo 'startTime: '. date('d/m/Y H:i:s', $playerQuest->startTime) .'<br />';

                    $endTime = ($playerQuest->endTime) ? date('d/m/Y H:i:s', $playerQuest->endTime) : 'not ended';

                    echo 'endTime: '. $endTime .'<br />';

                    $stepN = 1;

                    foreach($quest->get_steps($playerQuest->name) as $k=>$step){


                        if($step->status == 'pending' || $step->status == 'permanent'){

                            echo 'step '. $stepN .': '. $step->name .'<br />';
                        }
                        else{

                            echo 'step '. $stepN .': <s>'. $step->name .'</s> ('. date('d/m/Y H:i:s', $step->endTime) .')<br />';
                        }

                        $stepN++;
                    }


                    echo '<a href="#" OnClick="document.getElementById(\'input-line\').value = \'quest player '. $player->id .' reset '. $playerQuest->id .'\'; document.getElementById(\'input-line\').focus()">reset</a> ';

                    echo '<a href="#" OnClick="document.getElementById(\'input-line\').value = \'quest player '. $player->id .' nextstep '. $playerQuest->id .'\'; document.getElementById(\'input-line\').focus()">nextstep</a> ';

                    echo '<a href="#" OnClick="document.getElementById(\'input-line\').value = \'quest player '. $player->id .' newstep '. $playerQuest->id .' '. htmlentities('"new step"') .'\'; document.getElementById(\'input-line\').focus()">newstep</a> ';


                    echo '<a href="#" OnClick="document.getElementById(\'input-line\').value = \'quest player '. $player->id .' permanent '. $playerQuest->id .'\'; document.getElementById(\'input-line\').focus()">permanent</a> ';

                    return ob_get_clean();
                }

                elseif($argumentValues[2] == 'nextstep'){


                    if(!isset($argumentValues[3])){

                        return '<font color="red">error: missing argument "questId or questName". ie: quest player Orcrist nextstep "La Traque de Neach"</font>';
                    }


                    ob_start();

                    if(!$infoQuest = Quest::get_quest($argumentValues[3])){

                        return '<font color="orange">aborting: quest "'. $argumentValues[3] .'" does not exists.</font>';
                    }

                    $playerQuest = $quest->get_player_quest($infoQuest->name);


                    foreach($quest->get_steps($playerQuest->name) as $k=>$step){


                        if($step->status == 'pending'){


                            $quest->edit_step($playerQuest->name, $step->name, 'status', 'complete');


                            $quest->complete($playerQuest->name);


                            return $player->data->name .' quest "<a href="#" OnClick="document.getElementById(\'input-line\').value = \'quest player '. $player->id .' infos '. $playerQuest->id .'\'; document.getElementById(\'input-line\').focus()">'. $playerQuest->name .'</a>" step "'. $step->name .'" status: pending -> complete';
                        }
                    }


                    return 'all steps are completed';


                    return ob_get_clean();
                }


                elseif($argumentValues[2] == 'newstep'){


                    if(!isset($argumentValues[3])){

                        return '<font color="red">error: missing argument "questId or questName". ie: quest player Orcrist newstep "La Traque de Neach" "Parler à Shariel"</font>';
                    }

                    if(!isset($argumentValues[4])){

                        return '<font color="red">error: missing argument "questName". ie: quest player Orcrist newstep "La Traque de Neach" "Parler à Shariel"</font>';
                    }


                    ob_start();

                    if(!$infoQuest = Quest::get_quest($argumentValues[3])){

                        return '<font color="orange">aborting: quest "'. $argumentValues[3] .'" does not exists.</font>';
                    }

                    if(!$playerQuest = $quest->get_player_quest($infoQuest->name)){

                        return '<font color="orange">aborting: playerQuest "'. $argumentValues[3] .'" does not exists.</font>';
                    }

                    if(!$quest->put_step($playerQuest->name, $argumentValues[4])){

                        return '<font color="orange">something went wrong with $quest->put_step()</font>';
                    }

                    return $player->data->name .' quest <a href="#" OnClick="document.getElementById(\'input-line\').value = \'quest player '. $player->id .' infos '. $playerQuest->id .'\'; document.getElementById(\'input-line\').focus()">"'. $playerQuest->name .'"</a> new step successfully added';

                }


                elseif($argumentValues[2] == 'reset'){


                    if(!isset($argumentValues[3])){

                        return '<font color="red">error: missing argument "questId or questName". ie: quest player Orcrist reset "La Traque de Neach"</font>';
                    }

                    if(!$playerQuest = Quest::get_quest($argumentValues[3])){

                        return '<font color="orange">aborting: quest "'. $argumentValues[3] .'" does not exists.</font>';
                    }


                    if(!$quest->reset_quest($playerQuest->name)){

                        return '<font color="orange">something went wrong with $quest->reset()</font>';
                    }


                    return $player->data->name .' quest <a href="#" OnClick="document.getElementById(\'input-line\').value = \'quest player '. $player->id .' infos '. $playerQuest->id .'\'; document.getElementById(\'input-line\').focus()">"'. $playerQuest->name .'"</a> successfully reseted';
                }

                elseif($argumentValues[2] == 'permanent'){


                    if(!isset($argumentValues[3])){

                        return '<font color="red">error: missing argument "questId or questName". ie: quest player Orcrist reset "La Traque de Neach"</font>';
                    }

                    if(!$playerQuest = Quest::get_quest($argumentValues[3])){

                        return '<font color="orange">aborting: quest "'. $argumentValues[3] .'" does not exists.</font>';
                    }


                    if(!$quest->permanent($playerQuest->name)){

                        return '<font color="orange">something went wrong with $quest->permanent()</font>';
                    }


                    return $player->data->name .' quest <a href="#" OnClick="document.getElementById(\'input-line\').value = \'quest player '. $player->id .' infos '. $playerQuest->id .'\'; document.getElementById(\'input-line\').focus()">"'. $playerQuest->name .'"</a> made successfully permanent';
                }
            }

            ob_start();

            echo 'quests for player '. $player->data->name .':<br />';

            foreach($quest->get_player_quests() as $e){


                echo '#'. $e->id .' <a href="#" OnClick="document.getElementById(\'input-line\').value = \'quest player '. $player->id .' infos '. $e->id .'\'; document.getElementById(\'input-line\').focus()">"'. $e->name .'"</a> ('. $e->status .')<br />';
            }

            return ob_get_clean();

        }



        return '<font color="orange">no changes detected</font>';
    }
}
