<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Entity\StructureType;
use App\Interface\ActorInterface;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;
use App\Service\ItemInstanceService;
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
 * **Partage du travail avec {@see TargetTypeCondition}.** Celle-ci sait
 * nommer les familles depuis qu'un correctif l'a dotée du vocabulaire, et
 * `reparer` s'en servait faute de mieux. Deux gardes pour une règle, c'est une
 * de trop, et c'est la mauvaise qui restait : une liste dans la donnée d'une
 * action ne peut pas être contredite par un type, alors que la promesse est
 * qu'un type PUISSE contredire sa famille — dans les deux sens.
 *
 * Chacune répond donc à sa question :
 *  - `TargetType` : quelles SORTES de choses cette action atteint. `reparer`
 *    dit `structure` — l'enveloppe large, sans quoi cocher « réparable » sur un
 *    type de ressource ne ferait rien et la case mentirait ;
 *  - celle-ci : cette chose-LÀ s'entretient-elle.
 *
 * Posée en `display_context` : le bouton ne s'affiche pas sur ce qui ne se
 * répare pas, plutôt que de s'afficher et d'échouer au clic.
 */
class RequiresRepairableTargetCondition extends BaseCondition implements HasParameterSchemaInterface
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

        /* Un OBJET se répare, point : il est manufacturé, donc il se rafistole.
         * Où il se trouve n'y change rien — le plateau est seulement l'endroit
         * où l'on sait le désigner aujourd'hui.
         *
         * Traité à part parce que son type ne vit pas dans le même catalogue :
         * un exemplaire porte un type d'`items`, que `getRaceByName()` ne
         * trouvera jamais. Sans cette ligne, la recherche rendrait `null` et un
         * coffre cesserait d'être réparable. Le jour où un objet devra pouvoir
         * dire « moi, non », c'est une colonne `repairable` sur `items` — la
         * même mécanique, dans l'autre catalogue.
         *
         * COMBIEN il lui reste ne se décide pas ici : c'est
         * {@see RequiresDamagedTargetCondition} qui refuse l'intact et le brisé. */
        if ((string) ($target->data->player_type ?? '') === ItemInstanceService::ENTITY_TYPE) {
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
