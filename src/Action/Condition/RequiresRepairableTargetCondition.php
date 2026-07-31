<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Entity\StructureType;
use App\Interface\ActorInterface;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterSchema;
use App\Service\RaceService;

/**
 * On ne répare que ce qui se répare — et c'est le TYPE qui le dit.
 *
 * `reparer` ne filtrait que par CATÉGORIE (`TargetType: structure`). C'était
 * juste tant que les seules structures étaient bâties ; depuis que ressources,
 * décors et plantes sont des entités, ils sont tombés du même côté de l'arbre.
 * On pouvait réparer une fleur, et y laisser une action.
 *
 * La catégorie ne peut pas trancher : elle n'a que deux valeurs, et les deux
 * sont justes. La réponse appartient au type, comme le rendement ou la
 * repousse — ajouter un type de bâtiment doit suffire à le rendre réparable,
 * sans toucher à une liste ailleurs.
 *
 * Posée en `display_context` : le bouton ne s'affiche pas sur ce qui ne se
 * répare pas, plutôt que de s'afficher et d'échouer au clic.
 */
class RequiresRepairableTargetCondition extends BaseCondition implements HasParameterSchema
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function check(
        ActorInterface $actor,
        ?ActorInterface $target,
        ActionCondition $condition,
        ConditionObject $conditionObject
    ): ConditionResult {
        $preConditionResult = parent::check($actor, $target, $condition, $conditionObject);

        if (!$preConditionResult->isSuccess()) {
            return $preConditionResult;
        }

        if ($target === null) {
            return new ConditionResult(false, array(), array("Il n'y a rien à réparer ici."));
        }

        /* Un OBJET se répare, point : il a une vie — sa durabilité — et une vie
         * en dessous de son maximum se soigne. Où il se trouve n'y change rien ;
         * le plateau est seulement l'endroit où l'on sait le désigner
         * aujourd'hui.
         *
         * Traité à part parce qu'il n'a pas de type au catalogue :
         * `UniqueObjectService` l'inscrit sous la race « objet », qui n'existe
         * dans `races` pour personne. Sans cette ligne, la recherche rendrait
         * `null` et un coffre cesserait d'être réparable.
         *
         * COMBIEN il lui reste ne se décide pas ici : c'est
         * {@see RequiresDamagedTargetCondition} qui refuse l'intact et le brisé. */
        if ((string) ($target->data->player_type ?? '') === 'unique') {
            return new ConditionResult(true, array(), array());
        }

        $race = (string) ($target->data->race ?? '');
        $type = $race === '' ? null : (new RaceService())->getRaceByName($race);

        /* Un type inconnu au catalogue ne se répare pas : mieux vaut refuser
         * que rendre réparable, par défaut, tout ce qu'on n'a pas su lire. */
        if (!$type instanceof StructureType || !$type->isRepairable()) {
            return new ConditionResult(
                false,
                array(),
                array('Cela ne se répare pas.')
            );
        }

        return new ConditionResult(true, array(), array());
    }
}
