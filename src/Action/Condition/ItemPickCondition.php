<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Schema\FieldType;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use App\Service\InventoryService;
use Classes\Item;

/**
 * L'objet comme PARAMÈTRE D'EXÉCUTION d'une action générique
 * (docs/design-generic-item-actions.md) : le client POSTe `itemId`, la
 * condition valide possession ET admissibilité côté serveur, puis
 * dépose l'objet sur le ConditionObject — RequiresItem (sans paramètre
 * `item` statique) et PlaceStructure/ApplyConsumable consomment CE
 * résultat, jamais une relecture du POST. Même patron que
 * BuildSitePick/BuildSiteCondition pour la case de construction.
 *
 * `kind` restreint ce que l'action accepte : un itemId forgé ne doit
 * permettre ni de bâtir l'inconstructible, ni de « consommer » l'or.
 */
class ItemPickCondition extends BaseCondition implements HasParameterSchema
{
    public const KIND_CONSTRUCTIBLE = 'constructible';
    public const KIND_CONSOMMABLE = 'consommable';

    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('kind', FieldType::STRING, 'Nature exigée', required: true,
                help: self::KIND_CONSTRUCTIBLE . ' | ' . self::KIND_CONSOMMABLE),
        );
    }

    /** L'id d'objet fourni à la requête, ou null. */
    public static function requestedItemId(): ?int
    {
        $raw = $_POST['itemId'] ?? null;

        return is_numeric($raw) ? (int) $raw : null;
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $condition->setBlocking(true);

        $itemId = self::requestedItemId();
        if ($itemId === null) {
            return new ConditionResult(false, array(), ['Aucun objet fourni pour cette action.']);
        }

        $item = new Item($itemId);
        if (empty($item->row)) {
            return new ConditionResult(false, array(), ["Objet inconnu au catalogue (#{$itemId})."]);
        }
        $item->get_data();

        // Possession : sur la pile, comme les coûts (RequiresItem, règle P5).
        if ($item->get_n($actor, includeInstances: false) < 1) {
            return new ConditionResult(false, array(), ['Vous ne possédez pas ' . $item->data->name . '.']);
        }

        $kind = (string) ($condition->getParameters()['kind'] ?? '');
        $admissible = match ($kind) {
            self::KIND_CONSTRUCTIBLE => ($item->data->type ?? '') === Item::TYPE_CONSTRUCTIBLE,
            self::KIND_CONSOMMABLE => InventoryService::useKind($item) === InventoryService::USE_CONSUME,
            default => false,
        };
        if (!$admissible) {
            return new ConditionResult(false, array(), [$item->data->name . ' ne convient pas à cette action.']);
        }

        $conditionObject->setPickedItem($item);

        return new ConditionResult(true, array(), array());
    }
}
