<?php

class TpCmd extends Command
{
    public function __construct() {
        parent::__construct("tp",[new Argument('mat',false), new Argument('coords',false)]);
    }

    public function execute(  array $argumentValues ) : string
    {
        $player=parent::getPlayer($argumentValues[0]);

        $player->get_data();


        $coordsTbl = explode(',', $argumentValues[1]);

        if(count($coordsTbl) != 4){

            return '<font color="red">invalid coords (must be x,y,z,plan)</font>';
        }

        list($x, $y, $z, $plan) = $coordsTbl;

        $coords = (object) array(
            'x'=>$x,
            'y'=>$y,
            'z'=>$z,
            'plan'=>$plan
        );


        // clean function outputs
        ob_start();

        $player->go($coords);

        ob_clean();


        return 'tp '. $player->data->name .' to '. $x .','. $y .','. $z .','. $plan;
    }
}
