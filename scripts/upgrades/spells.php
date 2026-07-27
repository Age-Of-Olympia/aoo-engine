<?php

use App\Service\ActionService;
use App\Service\ActionPassiveService;
use App\View\Action\ActionCostView;
use App\Service\OutcomeInstructionService;
use Classes\Str;

if (isset($_GET['forget']) && !empty($_POST['spell'])) {
    if ($player->have_spell($_POST['spell'])) {
        $player->end_spell($_POST['spell']);
        
        ob_clean(); 
        exit('success');
    }
}

if (isset($_GET['forget_p']) && !empty($_POST['passive'])) {
    if ($player->have_action_passive($_POST['passive'])) {
        $player->end_action_passive($_POST['passive']);
        
        ob_clean(); 
        exit('success');
    }
}

$spellList = $player->get_spells();
$spellsN = count($spellList);
$trStyle = '';
$buttonStyle = '';

$maxColSpan = 8;
if(isset($_GET['forget'])){
    $maxColSpan++;
}

$maxColSpanP = 7;
if(isset($_GET['forget_p'])){
    $maxColSpanP++;
}

$passivesN = (new ActionPassiveService())->getActionPassiveCount($player->getId());
$nbComp = $spellsN + $passivesN;
$numberOfSpellsAvailable = NUMBER_MAX_COMP - $nbComp;
$maxSpells = NUMBER_MAX_COMP;

if($numberOfSpellsAvailable < 0){
    echo '<h2 style="color: red; text-align: center; margin: 10px 0; font-family: sans-serif; font-size: 1.2em;">Vous dépassez la limite de compétences (sorts + passifs cumulés, max '. $maxSpells .'). Oubliez-en pour repasser sous la limite.</h2>';
    $trStyle = (!isset($_GET['forget'])) ? 'style="opacity: 0.5;"' : '';
    $buttonStyle = 'class="blink" style="color: red;"';
} else {
    echo '<h2 style="color: black; text-align: center; margin: 10px 0; font-family: sans-serif; font-size: 1.2em;">Le maximum de compétences (sorts, techniques et passifs cumulés) est de '. $maxSpells .'.';
    if ($nbComp >= $maxSpells) {
        echo '<br />Vous avez atteint le maximum de compétences.';
    }
    echo '</h2>';
}

echo '<table class="box-shadow marbre" border="1" cellspacing="0" align="center">';
echo '<tr><th colspan="'.$maxColSpan.'" style="background-color: rgba(0,0,139,0.1);"><font color="blue">Sorts et Techniques Possédés</font></th></tr>';
echo '<tr><th colspan="2">Sort</th><th></th><th>Coût</th><th>Bonus</th><th>Effet</th><th>Type</th><th>Niveau</th>';

if(isset($_GET['forget'])){
    echo '<th>Action</th>';
}

echo '</tr>';

$actionService = new ActionService();
$costView = new ActionCostView($actionService);
foreach($spellList as $e){
    $spell = $actionService->getActionByName($e);

    if ($spell == null) {
        echo '<tr '. $trStyle .'><td colspan="'.$maxColSpan.'">Désolé, il y a un soucis : problème à remonter aux admins : le sort "'.$e.'" semble mal configuré.</td></tr>';
        continue;
    }

    $img = (file_exists('img/spells/'.$e.'.jpeg') ? 'img/spells/'. $e .'.jpeg' : 'img/spells/todo.jpeg');

    $cost = $costView->forAction($spell);

    $outcomes = $spell->getOnSuccessOutcomes();

    $bonusDamages = "";
    $bonusHeal = "";

    $outcomeInstructionService = new OutcomeInstructionService();

    // getOutcomeInstructionByTypeByOutcome renvoie un tableau d'instructions : on lit les params du premier
    $instructionLifeLoss = $outcomeInstructionService->getOutcomeInstructionByTypeByOutcome("LifeLossOutcomeInstruction", $outcomes[0]->getId());
    if (!empty($instructionLifeLoss)) {
        $instructionParameters = $instructionLifeLoss[0]->getParameters();
        if (isset($instructionParameters['bonusDamagesTrait'])) {
            $bonusDamages = $instructionParameters['bonusDamagesTrait'];
        }
    }

    $instructionHealing = $outcomeInstructionService->getOutcomeInstructionByTypeByOutcome("HealingOutcomeInstruction", $outcomes[0]->getId());
    if (!empty($instructionHealing)) {
        $instructionParameters = $instructionHealing[0]->getParameters();
        if (isset($instructionParameters['bonusHealingTrait'])) {
            $bonusHeal = $instructionParameters['bonusHealingTrait'];
        }
    }

    // heal/buff/spell sont des sorts ; seul 'technique' est une technique (#287)
    $type = in_array($spell->getOrmType(), ['spell', 'heal', 'buff'], true) ? "Sort" : "Technique";

    // Effets posés par le sort (instructions applystatus) : icône + nom + tooltip explicatif
    $effectBadges = [];
    $seenEffects = [];
    foreach ($outcomes as $outcome) {
        $statusInstructions = $outcomeInstructionService->getOutcomeInstructionByTypeByOutcome("ApplyStatusOutcomeInstruction", $outcome->getId());
        foreach ($statusInstructions as $status) {
            $statusParams = $status->getParameters();

            // gère la forme récente {"effect":"x","apply":true} et la forme legacy {"x":true}
            if (array_key_exists('effect', $statusParams)) {
                $effectName = (string) $statusParams['effect'];
                $apply = filter_var($statusParams['apply'] ?? true, FILTER_VALIDATE_BOOLEAN);
            } else {
                $effectName = (string) array_key_first($statusParams);
                $apply = filter_var($statusParams[$effectName] ?? true, FILTER_VALIDATE_BOOLEAN);
            }

            // seulement les effets réellement appliqués, une fois chacun, avec une icône connue
            if (!$apply || $effectName === '' || isset($seenEffects[$effectName]) || !isset(EFFECTS_RA_FONT[$effectName])) {
                continue;
            }
            $seenEffects[$effectName] = true;

            // "Titre<br />Description" : le titre sert de libellé, le tout de tooltip (texte brut)
            $label = isset(EFFECTS_TXT[$effectName])
                ? explode('<br />', EFFECTS_TXT[$effectName])[0]
                : ucfirst(str_replace('_', ' ', $effectName));
            $tooltip = isset(EFFECTS_TXT[$effectName])
                ? str_replace('<br />', ' : ', EFFECTS_TXT[$effectName])
                : $label;

            // valeur de l'effet (ex. Protection x2) : essentielle pour les effets qui stackent.
            // Préfixe cohérent avec l'exécution (ApplyStatusOutcomeInstruction) :
            // '+' si l'effet est cumulable (stackable), 'x' sinon.
            // scalaire => (x2)/(+2) ; tableau de nombres (aléatoire, ex. [1,2]) => (x1-2)
            $value = $statusParams['value'] ?? 1;
            $prefix = filter_var($statusParams['stackable'] ?? false, FILTER_VALIDATE_BOOLEAN) ? '+' : 'x';
            if (is_numeric($value)) {
                $valueSuffix = ' ('. $prefix . $value .')';
            } elseif (is_array($value) && $value !== [] && count(array_filter($value, 'is_numeric')) === count($value)) {
                $valueSuffix = ' ('. $prefix . implode('-', $value) .')';
            } else {
                $valueSuffix = '';
            }

            // - le tooltip et l'icône rpg-awesome utilisent tous deux ::before :
            //   tooltip sur le span parent, icône dans un span enfant
            // - le span parent reste en white-space normal pour que la bulle (::after,
            //   qui hérite du white-space) puisse wrapper ; le nowrap va sur un span
            //   interne pour garder icône + nom sur la même ligne
            $effectBadges[] = '<span flow="up" tooltip="'. htmlspecialchars($tooltip, ENT_QUOTES) .'" style="text-decoration:none;cursor:help;">'
                . '<span style="white-space:nowrap;"><span class="ra '. EFFECTS_RA_FONT[$effectName] .'"></span> '. htmlspecialchars($label) . $valueSuffix .'</span>'
                . '</span>';
        }
    }

    // Cellule Effet : texte descriptif du sort + badges des effets posés.
    // (le texte doit rester de la prose, sans réénumérer les effets)
    $effetCell = $spell->getText();
    if (!empty($effectBadges)) {
        $effetCell .= '<br />' . implode(' ; ', $effectBadges);
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
            '. (new \App\View\Action\ActionIconView())->forAction($spell, 'span') .'
        </td>
        <td>
            ' . $cost . '
        </td>
        ';

        // Le bonus peut être un entier (+4, -3), un trait nu ("m" => +M)
        // ou un tableau [trait, diviseur] (["m", 3] => +M/3, bonus basé sur une carac)
        $bonus = '';
        $bonusValue = ($bonusHeal !== "") ? $bonusHeal : $bonusDamages;

        if ($bonusValue !== "" && $bonusValue !== null) {
            if (is_array($bonusValue)) {
                $bonus = '+'. strtoupper((string) $bonusValue[0]) . (isset($bonusValue[1]) ? '/'. $bonusValue[1] : '');
            } elseif (is_numeric($bonusValue)) {
                $bonus = ($bonusValue < 0) ? (string) (int) $bonusValue : '+'. (int) $bonusValue;
            } else {
                $bonus = '+'. strtoupper((string) $bonusValue);
            }
        }

        echo '<td>'. $bonus .'</td>';


        echo '
        <td align="left">
            '. $effetCell .'
        </td>
        ';

        echo '
        <td>
            '. $type .'
        </td>
        ';

        echo '
        <td align="center">
            '. $spell->getLevel() .'
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
    echo '<tr><th colspan="2">Passif</th><th>Description</th><th>Catégorie</th><th>Niveau</th>';
    
    if(isset($_GET['forget_p'])){
        echo '<th>Action</th>';
    }
    echo '</tr>';

    foreach($passives as $passive) {

        $imgP = (file_exists('img/passives/'.$passive->getName().'.jpeg') ? 'img/passives/'.$passive->getName().'.jpeg' : 'img/spells/todo.jpeg');
        
        echo '
        <tr>
            <td width="50"><img src="'. $imgP .'" width="50" /></td>
            <td align="left"><b>'. $passive->getDisplayName() .'</b></td>
            <td align="center">'. $passive->getText() .'</td>
            <td align="center"><strong>'. $passive->getCategoryRender() .'</strong></td>
            <td align="center" style="font-size: 0.9em; max-width: 300px;">'. $passive->getLevel() .'</td>';
        
        if(isset($_GET['forget_p'])){

            echo '
            <td valign="top">
                <input
                    type="button"
                    class="forget"
                    data-passive="'. $passive->getName() .'"
                    data-name="'. $passive->getDisplayName() .'"
                    value="Oublier"
                    style="height: 50px;"
                    />
            </td>
            ';
        }

        echo '</tr>';
    }

    if(!isset($_GET['forget_p'])){

    echo '
    <tr>
        <td colspan="'.$maxColSpanP.'" align="right">

            <a href="upgrades.php?spells&forget_p"><button '. $buttonStyle .'>Oublier un passif</button></a>
        </td>
    </tr>
    ';
    }

    echo '</table>';
}

?>
<script src="js/forget_spells.js"></script>
<?php

echo Str::minify(ob_get_clean());
