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

$maxColSpan = 7;
if(isset($_GET['forget'])){
    $maxColSpan++;
}

$numberOfSpellsAvailable = $player->get_spells_available($spellsN);
$maxSpells = $player->get_max_spells();

if($numberOfSpellsAvailable < 0){
    echo '<tr><th colspan="'.$maxColSpan.'"><font color="red">Vous ne pouvez pas utiliser vos sorts (max.'. $maxSpells .')</font></th>';
    $trStyle = (!isset($_GET['forget'])) ? 'style="opacity: 0.5;"' : '';
    $buttonStyle = 'class="blink" style="color: red;"';
} else {
    echo '<tr><th colspan="'.$maxColSpan.'"><font color="blue">Le maximum de sorts/techniques que vous pouvez utiliser est de '. $maxSpells .'.</font>';
    if ($maxSpells == $spellsN) {
        echo '<br />Vous avez atteint le maximum de sorts/techniques que vous pouvez utiliser.';
    }
    echo '</th>';
}
echo '</tr>';

echo '<tr><th colspan="2">Sort</th><th></th><th>Coût</th><th>Bonus</th><th>Effet</th><th>Type</th>';

if(isset($_GET['forget'])){
    echo '<th>Action</th>';
}

echo '</tr>';

$actionService = new ActionService();
foreach($spellList as $e){
    $spell = $actionService->getActionByName($e);

    if ($spell == null) {
        echo '<tr '. $trStyle .'><td colspan="'.$maxColSpan.'">Désolé, il y a un soucis : problème à remonter aux admins : le sort "'.$e.'" semble mal configuré.</td></tr>';
        continue;
    }

    $img = 'img/spells/'. $e .'.jpeg';

    $costArray = $actionService->getCostsArray(null, $spell);

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

    $type = "Technique";
    if ($spell->getOrmType() == 'spell') {
        $type = "Sort";
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

        echo '
        <td>
            '. $type .'
        </td>
        ';


        if(isset($_GET['forget'])){

            echo '
            <td valign="top">
                <input
                    type="button"
                    class="forget"
                    data-spell="'. $e .'"
                    data-name="'. $spell->getDisplayName() .'"
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
        <td colspan="'.$maxColSpan.'" align="right">

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
