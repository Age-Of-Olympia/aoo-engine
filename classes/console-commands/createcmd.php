<?php

class CreateCmd extends Command
{
    public function __construct() {
        parent::__construct("create",[new Argument('type',false), new Argument('name',false), new Argument('race',false)]);
    }

    public function execute(  array $argumentValues ) : string
    {

        $action = $argumentValues[0];


        $raceJson = json()->decode('races', $argumentValues[2]);

        if(!$raceJson){

            return '<font color="red">race '. $argumentValues[2] .' not found</font>';
        }


        if($action == 'player'){

            $lastId = Player::put_player($argumentValues[1], $argumentValues[2]);
        }

        elseif($action == 'pnj'){

            $lastId = Player::put_player($argumentValues[1], $argumentValues[2], $pnj=true);
        }


        return 'player '. $argumentValues[1] .' ('. $raceJson->name .') created: mat.'. $lastId;
    }
}
