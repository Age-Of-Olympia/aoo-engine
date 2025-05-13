<?php

use App\Service\ActionService;
use App\Service\OutcomeInstructionService;

if(isset($_GET['forget']) && !empty($_POST['spell'])){

    include('scripts/upgrades/forget_spell.php');

    echo Str::minify(ob_get_clean());

    exit();
}

echo '<table class="box-shadow marbre" border="1" cellspacing="0" align="center">';

$spellList = $player->get_spells();
$spellsN = count($spellList);
$trStyle = '';
$buttonStyle = '';

$maxSpells = $player->get_max_spells($spellsN);
$max = $maxSpells + $spellsN;

if($maxSpells < 0){
    echo '<tr><th colspan="6"><font color="red">Vous ne pouvez pas utiliser vos sorts (max.'. $max .')</font></th>';
    $trStyle = (!isset($_GET['forget'])) ? 'style="opacity: 0.5;"' : '';
    $buttonStyle = 'class="blink" style="color: red;"';
} else {
    echo '<tr><th colspan="6"><font color="blue">Le maximum de sorts que vous pouvez utiliser est de '. $spellsN .'.</font>';
    if ($max == $spellsN) {
        echo '<br />Vous avez atteint le maximum de sorts que vous pouvez utiliser.';
    }
    echo '</th>';
}



echo '<tr><th colspan="2">Sort</th><th></th><th>Coût</th><th>Bonus</th><th colspan="2">Effet</th></tr>';




foreach($spellList as $e){


    $actionService = new ActionService();
    $spell = $actionService->getActionByName($e);

    $img = 'img/spells/'. $e .'.jpeg';

    $conditions = $spell->getConditions();

    foreach($conditions as $condition) {
        $conditionType = $condition->getConditionType();
        if ($conditionType == 'RequiresTraitValue') {
            $conditionParameters = $condition->getParameters();
            $costArray = array();
            foreach ($conditionParameters as $key => $value) {
                if ($key == "uses_fatigue") {
                    continue;
                }
                if ($key == "fatigue") {
                    continue;
                }
                array_push($costArray, $value . CARACS[$key]);
            }
            break;
        }
    }

    $outcomes = $spell->getOnSuccessOutcomes();

    $bonusDamages = "";
    $bonusHeal = "";

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

    echo '
    <tr '. $trStyle .'>
        <td valign="top" width="50">
            <img src="'. $img .'" width="50" />
        </td>
        <td align="left">
            '. $spell->getDisplayName() .'
        </td>
        <td>
            <span class="ra '. $spell->getIcon() .'"></span>
        </td>
        <td>
            '. implode(', ', $costArray) .'
        </td>
        ';

        $bonus = '';

        if($bonusDamages != ""){
            $bonus = '+'. $bonusDamages;
        }
        
        if($bonusHeal != ""){
            $bonus = '+'. $bonusHeal;
        }


        echo '<td>'. $bonus .'</td>';


        echo '
        <td align="left">
            '. $spell->getText() .'
        </td>
        ';


        if(isset($_GET['forget'])){

            echo '
            <td valign="top">
                <input
                    type="button"
                    class="forget"
                    data-spell="'. $e .'"
                    data-name="'. $spellJson->name .'"
                    value="Oublier"
                    style="height: 50px;"
                    />
            </td>
            ';
        }

        echo '
    </tr>
    ';
}


if(!isset($_GET['forget'])){

    echo '
    <tr>
        <td colspan="6" align="right">

            <a href="upgrades.php?spells&forget"><button '. $buttonStyle .'>Oublier un sort</button></a>
        </td>
    </tr>
    ';
}

echo '
</table>
';

?>
<script src="js/forget_spells.js"></script>
<?php

echo Str::minify(ob_get_clean());
