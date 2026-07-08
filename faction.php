<?php

use Classes\Ui;

require_once('config.php');


if(!empty($_GET['faction'])){

    $facJson = json()->decode('factions', $_GET['faction']);


    if(!$facJson){

        exit('error faction');
    }


    $ui = new Ui('Faction: '. $facJson->name);

    echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"> Retour</button></a></div>';


    include('scripts/faction/body.php');
}
else{

    $ui = new Ui('Factions');
}
