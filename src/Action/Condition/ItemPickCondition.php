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
    public const KIND_EQUIPEMENT = 'equipement';

    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('kind', FieldType::STRING, 'Nature exigée', required: true,
                help: self::KIND_CONSTRUCTIBLE . ' | ' . self::KIND_CONSOMMABLE . ' | ' . self::KIND_EQUIPEMENT),
        );
    }

    /** L'id d'objet fourni à la requête, ou null. */
    public static function requestedItemId(): ?int
    {
        $raw = $_POST['itemId'] ?? null;

        return is_numeric($raw) ? (int) $raw : null;
    }

    /** L'instance précise désignée au geste (ligne d'instance cliquée), ou null. */
    public static function requestedInstanceId(): ?int
    {
        $raw = $_POST['instanceId'] ?? null;

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

        $kind = (string) ($condition->getParameters()['kind'] ?? '');

        // Possession : sur la pile, comme les coûts (RequiresItem, règle
        // P5) — SAUF l'équipement, où l'exemplaire peut être une instance
        // individualisée (arme à durabilité) : instances comprises.
        $owned = $item->get_n($actor, includeInstances: $kind === self::KIND_EQUIPEMENT);
        if ($owned < 1) {
            return new ConditionResult(false, array(), ['Vous ne possédez pas ' . $item->data->name . '.']);
        }

        $admissible = match ($kind) {
            self::KIND_CONSTRUCTIBLE => ($item->data->type ?? '') === Item::TYPE_CONSTRUCTIBLE,
            self::KIND_CONSOMMABLE => InventoryService::useKind($item) === InventoryService::USE_CONSUME,
            self::KIND_EQUIPEMENT => InventoryService::useKind($item) === InventoryService::USE_EQUIP,
            default => false,
        };
        if (!$admissible) {
            return new ConditionResult(false, array(), [$item->data->name . ' ne convient pas à cette action.']);
        }

        $conditionObject->setPickedItem($item);
        $conditionObject->setPickedInstanceId(self::requestedInstanceId());

        return new ConditionResult(true, array(), array());
    }
}
