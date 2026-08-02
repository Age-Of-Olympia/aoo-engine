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
 * La branche seule ne suffit pas toujours : `reparer` la déclarait, et
 * remettait donc en état un arbre ou une plante — tout ce qui n'est pas un
 * personnage vit sous `structure`. Les FAMILLES s'écrivent donc aussi, par
 * discriminant ({@see \App\Enum\EntityCategory::structureFamilies()}) :
 * `['building','scenery','item']` répare ce qui se répare et rien d'autre.
 * Les deux vocabulaires cohabitent — une famille nommée suffit, la branche
 * reste le parapluie.
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
     * Ce qu'un refus nomme. Mêmes clés que les familles de structures, avec
     * leur article : un message parle de « une ressource », pas de
     * « Ressource ». EntityFamiliesVocabularyTest tient les deux listes
     * alignées.
     */
    private const REFUSAL_LABELS = [
        'building' => 'un bâtiment',
        'scenery'  => 'un décor',
        'resource' => 'une ressource',
        'plant'    => 'une plante',
        'item'     => 'un objet posé',
    ];

    /**
     * Le vocabulaire de la visée, en un seul endroit : branches, familles et
     * les deux visées exclusives. Le wiki des actions le lit aussi — deux
     * tables de libellés avaient déjà divergé.
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
                /* L'admin dit « seulement » là où le wiki dit la visée : la
                 * liste des valeurs, elle, reste la même. */
                options: array_merge(self::targetLabels(), [
                    self::KIND_SELF => 'Soi-même seulement',
                ]),
            ),
        );
    }

    /**
     * La cible est-elle atteinte par cette déclaration ? Règle UNIQUE, lue
     * aussi par {@see \App\Service\Action\ActionTargeting} : un bouton qui
     * s'affiche et une exécution qui refuse, c'est la même liste lue deux fois.
     *
     * Une famille nommée suffit ; sinon la branche répond.
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

        /* Le refus nomme la CIBLE, pas la branche : « ne peut pas viser une
         * structure » sur un arbre, alors que l'action répare les bâtiments,
         * décrivait mal ce qu'elle refusait. */
        return new ConditionResult(
            false,
            array(),
            ['Cette action ne peut pas viser ' . self::refusalLabel($playerType) . '.']
        );
    }

    /** Comment nommer ce qu'on refuse ; la branche répond pour ce qui n'a pas de libellé. */
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
