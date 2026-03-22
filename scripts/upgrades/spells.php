<?php

use App\Service\ActionService;
use App\Service\OutcomeInstructionService;
use Classes\Str;

if (isset($_GET['forget']) && !empty($_POST['spell'])) {

    if (!$player->have_spell($_POST['spell'])) {
        exit('error have spell');
    }
    $player->end_spell($_POST['spell']);

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

$numberOfSpellsAvailable = NUMBER_MAX_COMP - $spellsN;
$maxSpells = NUMBER_MAX_COMP;

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

    $img = (file_exists('img/spells/'.$e.'.jpeg') ? 'img/spells/'. $e .'.jpeg' : 'img/spells/todo.jpeg');

    $cost = $spell->getCost();

    $outcomes = $spell->getOnSuccessOutcomes();

    $bonusDamages = "";
    $bonusHeal = "";

    $outcomeInstructionService = new OutcomeInstructionService();

    $instructionLifeLoss = $outcomeInstructionService->getOutcomeInstructionByTypeByOutcome("LifeLossOutcomeInstruction", $outcomes[0]->getId());
    if (isset($instructionLifeLoss)) {
        $instructionParameters = [];
        if (is_object($instructionLifeLoss)) {
        $instructionParameters = $instructionLifeLoss->getParameters();
        } elseif (is_array($instructionLifeLoss)) {
            $instructionParameters =  $instructionLifeLoss;
        }
        if (isset($instructionParameters['bonusDamagesTrait'])) {
            $bonusDamages = $instructionParameters['bonusDamagesTrait'];
        }
    }

    $instructionHealing = $outcomeInstructionService->getOutcomeInstructionByTypeByOutcome("HealingOutcomeInstruction", $outcomes[0]->getId());
    if (isset($instructionHealing)) {
        $instructionParameters = [];
    
        if (is_object($instructionHealing)) {
            $instructionParameters = $instructionHealing->getParameters();
        } elseif (is_array($instructionHealing)) {
            $instructionParameters = $instructionHealing;
        }
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
            ' . $cost . '
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

echo '<div style="margin-top: 20px;"></div>';

$passives = $player->getPassives($player->id);

if (!empty($passives)) {
    echo '<table class="box-shadow marbre" border="1" cellspacing="0" align="center">';
    echo '<tr><th colspan="6" style="background-color: rgba(0,0,139,0.1);"><font color="blue">Compétences Passives Possédées</font></th></tr>';
    echo '<tr><th colspan="2">Passif</th><th>Description</th><th>Catégorie</th><th>Niveau</th></tr>';

    foreach($passives as $passive) {

        $imgP = (file_exists('img/passives/'.$passive->getName().'.jpeg') ? 'img/passives/'.$passive->getName().'.jpeg' : 'img/spells/todo.jpeg');
        
        echo '
        <tr>
            <td width="50"><img src="'. $imgP .'" width="50" /></td>
            <td align="left"><b>'. $passive->getDisplayName() .'</b></td>
            <td align="center">'. $passive->getText() .'</td>
            <td align="center"><strong>'. $passive->getCategoryRender() .'</strong></td>
            <td align="center" style="font-size: 0.9em; max-width: 300px;">'. $passive->getLevel() .'</td>
        </tr>';
    }
    echo '</table>';
}

?>
<script src="js/forget_spells.js"></script>
<?php

echo Str::minify(ob_get_clean());
