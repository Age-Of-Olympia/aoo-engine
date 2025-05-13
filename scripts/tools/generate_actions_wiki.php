<?php

use App\Service\ActionService;

echo '<textarea style="width: 100vw; height: 50vw;">';


foreach(RACES as $race){


    $raceJson = json()->decode('races', $race);

    echo '==== '. $raceJson->name .' ====
^ # ^ Nom de l\'action ^ Type ^ Coût ^ Bonus ^ Description ^
';

    $n = 1;

    foreach($raceJson->actionsPack as $action){

        $actionService = new ActionService();
        $actionData = $actionService->getActionByName($e);

        if($actionData->getOrmType() != 'sort' || $actionData->getOrmType() != 'technique'){
            continue;
        }

        $type = $actionData->getOrmType();

        $bonus = '';
        // ToDo : aller chercher dans les outcomes s'il y a des bonus de dégâts ou de soins

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
