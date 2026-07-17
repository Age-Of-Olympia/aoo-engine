<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterSchema;
use Classes\View;

/**
 * Valide la case de construction CHOISIE (mode choix de case,
 * js/build_picker.js → POST buildX/buildY) : adjacente ET libre —
 * exactement les cases .go que le masque a proposées. En condition
 * BLOQUANTE : un refus n'engage AUCUN coût (l'exécuteur paie après
 * les conditions, pas question de consommer l'objet pour une case
 * volée entre l'affichage et le clic). Sans coordonnées fournies
 * (mode automatique) : laisse passer, PlaceStructure choisira.
 */
class BuildSiteCondition extends BaseCondition implements HasParameterSchema
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        if (!isset($_POST['buildX'], $_POST['buildY'])) {
            return new ConditionResult(true, array(), array());
        }

        $coords = $actor->getCoords();

        $requested = ((int) $_POST['buildX']) . ',' . ((int) $_POST['buildY']);
        $around = View::get_coords_arround(clone $coords, 1);
        $taken = View::get_coords_taken(clone $coords);

        if (!in_array($requested, $around, true) || in_array($requested, $taken, true)) {
            $condition->setBlocking(true);

            return new ConditionResult(false, array(), ['Impossible de construire là — la case doit être adjacente et libre.']);
        }

        return new ConditionResult(true, array(), array());
    }
}
