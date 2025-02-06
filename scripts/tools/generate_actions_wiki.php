<?php

echo '<textarea style="width: 100vw; height: 50vw;">';


foreach(RACES as $race){


    $raceJson = json()->decode('races', $race);

    echo '==== '. $raceJson->name .' ====
^ # ^ Nom de l\'action ^ Type ^ Coût ^ Bonus ^ Description ^
';

    $n = 1;

    foreach($raceJson->actionsPack as $action){


        $actionJson = json()->decode('actions', $action);

        if(!isset($actionJson->type) || $actionJson->type != 'sort'){

            continue;
        }


        $type = (!empty($actionJson->subtype)) ? 'technique' : 'sort';


        $bonus = '';

        if(!empty($actionJson->bonusDamages)){

            $bonus = '+'. $actionJson->bonusDamages;
        }
        elseif(!empty($actionJson->bonusHeal)){

            $bonus = '+'. $actionJson->bonusHeal;
        }


        echo '| '. $n .' | {{https://age-of-olympia.net/img/spells/'. $action .'.jpeg}} '. $actionJson->name .' | '. $type .' | '. $actionJson->costs->pm .'PM | '. $bonus .' | '. $actionJson->text .' |
';

        $n++;
    }
}



echo '</textarea>';
