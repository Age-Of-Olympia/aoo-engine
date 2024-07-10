<?php

class EffectCmd extends Command
{
    public function __construct() {
        parent::__construct("effect",[new Argument('action',false), new Argument('player_id_or_name',false), new Argument('effect',false), new Argument('duration',true) ]);
    }

    public function execute(  array $argumentValues ) : string
    {
        $player=parent::getPlayer($argumentValues[1]);

        $player->get_data();

        if($argumentValues[0] == 'add'){
            if($player->have('effects', $argumentValues[2])){


                $player->end_effect($argumentValues[2]);

                return 'Effet '. $argumentValues[2] .' enlevé à '. $player->data->name .'';
            }

            else{

                // duration
                $duration = (!empty($argumentValues[3])) ? $argumentValues[3] : 0;

                $player->add_effect($argumentValues[2], $duration);

                return 'Effet '. $argumentValues[2] .' ajouté à '. $player->data->name .'';
            }
        }


        return 'action not found';

    }
}
