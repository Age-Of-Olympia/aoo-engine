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
 *   'structure' — buildings, scenery, resources, plants, placed objects
 *
 * A heal keeps ['character']; an attack that may also raze a palisade
 * declares ['character', 'structure']. Rows predating the param keep
 * today's behavior via the ['character'] default.
 *
 * FAMILIES may be named too, by discriminator
 * ({@see \App\Enum\EntityCategory::structureFamilies()}), for rules the branch
 * cannot express — everything that is not a character lives under `structure`.
 * Both vocabularies coexist: a named family is enough, the branch stays the
 * umbrella.
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

    /**
     * What a refusal names: same keys as the structure families, with their
     * article. EntityFamiliesVocabularyTest keeps the two lists aligned.
     */
    private const REFUSAL_LABELS = [
        'building' => 'un bâtiment',
        'scenery'  => 'un décor',
        'resource' => 'une ressource',
        'plant'    => 'une plante',
        'item'     => 'un objet posé',
    ];

    /**
     * The targeting vocabulary in one place: branches, families and the two
     * exclusive kinds. The action wiki reads it too — keep it the only table.
     *
     * @return array<string, string>
     */
    public static function targetLabels(): array
    {
        return array_merge(
            \App\Enum\EntityCategory::options(),
            \App\Enum\EntityCategory::structureFamilies(),
            [
                self::KIND_SELF => 'Soi-même',
                self::KIND_NONE => 'Sans cible',
            ],
        );
    }

    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField(
                'allowed',
                FieldType::ENUM,
                'Catégories de cibles autorisées',
                default: ['character'],
                multiple: true,
                /* The admin says "seulement" where the wiki states the aim;
                 * the set of values is the same. */
                options: array_merge(self::targetLabels(), [
                    self::KIND_SELF => 'Soi-même seulement',
                ]),
            ),
        );
    }

    /**
     * Is the target reached by this declaration? The single rule, read by
     * {@see \App\Service\Action\ActionTargeting} as well, so the button shown
     * and the execution allowed cannot disagree.
     *
     * A named family is enough; otherwise the branch answers.
     *
     * @param array<int, string> $allowed
     */
    public static function reaches(?string $playerType, array $allowed): bool
    {
        if ($allowed === []) {
            $allowed = [\App\Enum\EntityCategory::Character->value];
        }

        if (in_array((string) ($playerType ?? 'real'), $allowed, true)) {
            return true;
        }

        return in_array(
            \App\Enum\EntityCategory::fromPlayerType($playerType)->value,
            $allowed,
            true
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

        $playerType = (string) ($target->data->player_type ?? 'real');

        if (self::reaches($playerType, $allowed)) {
            /* A structure must hold its tile to be aimed at. What lies dropped
             * on the ground occupies nothing and is picked up, not targeted —
             * the same cells that decide obstruction decide this. */
            if (\App\Enum\EntityCategory::fromPlayerType($playerType)->isStructure() && !$this->holdsATile($target)) {
                $condition->setBlocking(true);

                return new ConditionResult(false, array(), ['Cet objet est au sol : on le ramasse, on ne le vise pas.']);
            }

            return new ConditionResult(true, array(), array());
        }

        $condition->setBlocking(true);

        /* Name the TARGET, not the branch: "une structure" reads wrong on a
         * tree when the action repairs buildings. */
        return new ConditionResult(
            false,
            array(),
            ['Cette action ne peut pas viser ' . self::refusalLabel($playerType) . '.']
        );
    }

    /** How to name what is refused; the branch answers for anything unlabelled. */
    private static function refusalLabel(string $playerType): string
    {
        return self::REFUSAL_LABELS[$playerType]
            ?? (\App\Enum\EntityCategory::fromPlayerType($playerType)->isStructure()
                ? 'une structure'
                : 'un personnage');
    }

    /** Simulated targets have no cells and are not meant to be looked up. */
    private function holdsATile(ActorInterface $target): bool
    {
        if ($target->isSimulated()) {
            return true;
        }

        return (int) \App\Factory\EntityManagerFactory::getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM entity_cells WHERE player_id = ?',
            [$target->getId()]
        ) > 0;
    }
}
