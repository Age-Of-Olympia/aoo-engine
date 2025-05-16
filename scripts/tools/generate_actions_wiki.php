<?php

use App\Service\ActionService;
use App\Service\OutcomeInstructionService;

echo '<textarea style="width: 100vw; height: 50vw;">';


foreach(RACES as $race){


    $raceJson = json()->decode('races', $race);

    echo '==== '. $raceJson->name .' ====
^ # ^ Nom de l\'action ^ Type ^ Coût ^ Bonus ^ Description ^
';

    $n = 1;

    $actionService = new ActionService();
    foreach($raceJson->actionsPack as $actionName){
        $actionData = $actionService->getActionByName($actionName);

        if($actionData->getOrmType() != 'spell' || $actionData->getOrmType() != 'technique'){
            continue;
        }

        $type = "Technique";
        if ($actionData->getOrmType() == 'spell') {
            $type = "Sort";
        }

        $bonus = '';

        $outcomes = $actionData->getOnSuccessOutcomes();

        $bonusDamages = "";
        $bonusHeal = "";

        $costArray = $actionService->getCostsArray(null, $actionData);

        $outcomeInstructionService = new OutcomeInstructionService();

        $instructionLifeLoss = $outcomeInstructionService->getOutcomeInstructionByTypeByOutcome("LifeLossOutcomeInstruction", $outcomes[0]->getId());
        if (isset($instructionLifeLoss)) {
            $instructionParameters = $instructionLifeLoss->getParameters();
            if (isset($instructionParameters['bonusDamagesTrait'])) {
                $bonusDamages = $instructionParameters['bonusDamagesTrait'];
            }
        }

        $instructionHealing = $outcomeInstructionService->getOutcomeInstructionByTypeByOutcome("HealingOutcomeInstruction", $outcomes[0]->getId());
        if (isset($instructionHealing)) {
            $instructionParameters = $instructionHealing->getParameters();
            if (isset($instructionParameters['bonusHealingTrait'])) {
                $bonusHeal = $instructionParameters['bonusHealingTrait'];
            }
        }

        if($bonusDamages != ""){
            $bonus = '+'. $bonusDamages;
        }
        elseif($bonusHeal != ""){
            $bonus = '+'. $bonusHeal;
        }


        echo '| '. $n .' | {{https://age-of-olympia.net/img/spells/'. $actionName .'.jpeg}} '. $actionData->getDisplayName() .' | '. $type .' | '. implode($costArray) . ' | '. $bonus .' | '. $actionJson->text .' |
';

        $n++;
    }
}



echo '</textarea>';
