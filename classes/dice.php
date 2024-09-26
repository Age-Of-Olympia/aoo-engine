<?php

class Dice{

    public int $n;

    function __construct($n){


        if(!is_numeric($n)){
            exit('error n');
        }


        $this->n = $n;
    }


    public function roll($d){


        if($d == 0){

            return array(0);
        }


        $dicesTbl = array();

        for( $i = 0; $i < $d; $i++ ){
            $dicesTbl[] = rand(1,$this->n);
        }

        return $dicesTbl;
    }
}
