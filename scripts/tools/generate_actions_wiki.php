<?php

echo '<textarea style="width: 100vw; height: 50vw;">';


foreach(RACES as $race){


    $raceJson = json()->decode('races', $race);

    echo '==== '. $raceJson->name .' ====
^ # ^ Nom de l\'action ^ Type ^ Coût ^ Description ^
';

    $n = 1;

    foreach($raceJson->actionsPack as $action){


        $actionJson = json()->decode('actions', $action);

        if(!isset($actionJson->type) || $actionJson->type != 'sort'){

            continue;
        }


        $type = (!empty($actionJson->subtype)) ? 'technique' : 'sort';


        echo '| '. $n .' | {{https://aootest.net/img/spells/'. $action .'.jpeg}} '. $actionJson->name .' | '. $type .' | '. $actionJson->costs->pm .'PM | '. $actionJson->text .' |
';

        $n++;
    }
}



echo '</textarea>';
