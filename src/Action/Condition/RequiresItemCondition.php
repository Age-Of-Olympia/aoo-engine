<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Schema\FieldType;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use Classes\Item;

/**
 * G1 (docs/design-items-instances.md §4) : l'action exige N exemplaires
 * d'un objet du catalogue dans l'inventaire de l'acteur, et les
 * consomme au paiement des coûts — RequiresAmmo généralisé à n'importe
 * quel objet.
 *
 * Sert les potions (consommer), les coûts de matériaux de construction
 * (construire une palissade = 10 bois) et de réparation, « ouvrir avec
 * la clé »… Le retrait passe par Item::add_item (transactionnel, purge
 * des piles à zéro) ; en simulation, l'écriture est absorbée par le
 * SimulationGuard comme tous les coûts.
 */
class RequiresItemCondition extends BaseCondition implements HasParameterSchema
{
    public bool $toRemove = false;

    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('item', FieldType::ITEM, 'Objet requis', required: true),
            new ParameterField('n', FieldType::INT, 'Quantité requise', default: 1),
            new ParameterField('consume', FieldType::BOOL, 'Consommer au paiement', default: true),
        );
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        // L'instance de condition est partagée entre les occurrences d'une
        // même action : sans remise à zéro, un échec NON bloquant hériterait
        // du toRemove d'une occurrence précédente et serait payé quand même.
        $this->toRemove = false;

        $params = $condition->getParameters();
        $itemId = $params['item'] ?? null;
        $n = max(1, (int) ($params['n'] ?? 1));

        if ($itemId === null) {
            return new ConditionResult(false, array(), ['Condition RequiresItem mal configurée (objet manquant).']);
        }

        $item = new Item((int) $itemId);
        if (empty($item->row)) {
            return new ConditionResult(false, array(), ["Objet inconnu au catalogue (#{$itemId})."]);
        }
        $item->get_data();

        // Pile uniquement (règle P5) : les coûts consomment des unités de
        // pile — une épée nommée ne part jamais silencieusement en matériau.
        $owned = $item->get_n($actor, includeInstances: false);
        if ($owned < $n) {
            return new ConditionResult(
                false,
                array(),
                ['Il vous faut ' . $n . ' × ' . $item->data->name . ' (' . $owned . ' en votre possession).']
            );
        }

        $this->toRemove = (bool) ($params['consume'] ?? true);

        return new ConditionResult(true, array(), array());
    }

    public function toRemove(): bool
    {
        return $this->toRemove;
    }

    public function applyCosts(ActorInterface $actor, ?ActorInterface $target, ActionCondition $conditionToPay): array
    {
        $params = $conditionToPay->getParameters();
        $n = max(1, (int) ($params['n'] ?? 1));

        $item = new Item((int) $params['item']);
        $item->get_data();
        $item->add_item($actor, -$n);

        return ['Vous dépensez ' . $n . ' × ' . $item->data->name . '.'];
    }
}
