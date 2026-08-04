<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;

/**
 * Valide la case de construction CHOISIE (mode choix de case,
 * js/build_picker.js → POST buildX/buildY) via {@see BuildSitePick},
 * la source unique de la règle. En condition BLOQUANTE : un refus
 * n'engage AUCUN coût (l'exécuteur paie après les conditions, pas
 * question de consommer l'objet pour une case volée entre l'affichage
 * et le clic). La case validée est déposée sur le ConditionObject —
 * PlaceStructure consomme CE résultat, pas une re-lecture du POST.
 * Sans coordonnées fournies (mode automatique) : laisse passer,
 * PlaceStructure choisira.
 */
class BuildSiteCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        if (!BuildSitePick::requested()) {
            return new ConditionResult(true, array(), array());
        }

        /* The footprint rule needs the TYPE being built: the picked item's
         * name IS it (constructible convention). ItemPick may not have run
         * yet, so fall back to the gesture's itemId. */
        $type = $conditionObject->getPickedItem()?->row->name;
        if ($type === null && is_numeric($_POST['itemId'] ?? null)) {
            $type = (new \Classes\Item((int) $_POST['itemId']))->row->name ?? null;
        }

        $goCoords = BuildSitePick::resolve($actor->getCoords(), $type !== null ? (string) $type : null);

        if ($goCoords === null) {
            $condition->setBlocking(true);

            return new ConditionResult(false, array(), [BuildSitePick::REFUSAL]);
        }

        $conditionObject->setBuildCoords($goCoords);

        return new ConditionResult(true, array(), array());
    }
}
