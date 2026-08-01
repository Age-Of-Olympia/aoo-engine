<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Enum\FieldType;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;

/**
 * Restricts an action to target categories of the GameEntity tree
 * (docs/design-buildings-entities.md §4.4) — the data-driven
 * replacement for id-sign conventions:
 *
 *   'character' — real players, tutorial players, NPCs
 *   'structure' — buildings, unique objects
 *
 * A heal keeps ['character']; an attack that may also raze a palisade
 * declares ['character', 'structure']; a future repair action declares
 * ['structure']. Rows predating the param keep today's behavior via
 * the ['character'] default.
 *
 * Two SORTES DE VISÉE s'ajoutent aux catégories d'entités (décision du
 * 2026-07-19, actions génériques) :
 *   'self' — l'action ne vise que son lanceur (consommer une potion) ;
 *   'none' — l'action n'a PAS de cible (construire produit sur la
 *            carte) : elle refuse toute cible désignée, seule
 *            l'exécution auto-ciblée d'action.php passe.
 * Exclusives : une visée self/none ne se combine pas aux catégories.
 *
 * Reads the player_type discriminator from the target's data (absent
 * on simulated targets, which default to 'real' — simulations always
 * model characters).
 */
class TargetTypeCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public const KIND_SELF = 'self';
    public const KIND_NONE = 'none';

    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField(
                'allowed',
                FieldType::ENUM,
                'Catégories de cibles autorisées',
                default: ['character'],
                multiple: true,
                options: array_merge(\App\Enum\EntityCategory::options(), [
                    self::KIND_SELF => 'Soi-même seulement',
                    self::KIND_NONE => 'Sans cible',
                ]),
            ),
        );
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $params = $condition->getParameters();
        $allowed = is_array($params['allowed'] ?? null) && $params['allowed'] !== []
            ? $params['allowed']
            : ['character'];

        // Visées exclusives : self (le lanceur uniquement) et none (aucune
        // cible) — dans les deux cas la seule exécution valide est celle où
        // action.php a auto-ciblé le lanceur (ou une simulation sur soi).
        if (in_array(self::KIND_SELF, $allowed, true) || in_array(self::KIND_NONE, $allowed, true)) {
            if ($target === null || $target->getId() === $actor->getId()) {
                return new ConditionResult(true, array(), array());
            }

            $condition->setBlocking(true);

            $errorMessage = in_array(self::KIND_NONE, $allowed, true)
                ? ['Cette action ne vise personne.']
                : ['Cette action ne s\'applique qu\'à vous-même.'];

            return new ConditionResult(false, array(), $errorMessage);
        }

        $category = \App\Enum\EntityCategory::fromPlayerType($target->data->player_type ?? 'real')->value;

        if (in_array($category, $allowed, true)) {
            return new ConditionResult(true, array(), array());
        }

        $condition->setBlocking(true);

        $errorMessage = $category === 'structure'
            ? ['Cette action ne peut pas viser une structure.']
            : ['Cette action ne peut viser qu\'une structure.'];

        return new ConditionResult(false, array(), $errorMessage);
    }
}
