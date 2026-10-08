<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use Classes\Item;
use Classes\Player;
use Doctrine\DBAL\Connection;

/**
 * The repair recipes a type carries, one per mode: raw materials, or gold
 * alone (the item `or`). A recipe is what one dose costs and the share of
 * the type's max PV it restores; a type without a recipe in a mode does not
 * mend that way.
 *
 * Keyed by type NAME, as entity_type_footprints: a building type and a
 * placed exemplar (whose type is its item) share the one join key the
 * world already uses, players.race.
 */
final class TypeRepairService
{
    public const MATERIALS = 'materials';
    public const GOLD = 'gold';
    public const MODES = [self::MATERIALS => 'Matériaux', self::GOLD => 'Or'];

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * @return array{percent: int, costs: array<string, int>}|null null when the type does not mend this way
     */
    public function recipeOf(string $typeName, string $mode): ?array
    {
        return $this->recipesOf($typeName)[$mode] ?? null;
    }

    /**
     * @return array<string, array{percent: int, costs: array<string, int>}> mode => recipe, only the modes declared
     */
    public function recipesOf(string $typeName): array
    {
        $recipes = [];
        foreach ($this->conn->fetchAllAssociative(
            'SELECT r.mode, r.percent, i.name, c.quantity
               FROM entity_type_repairs r
               JOIN entity_type_repair_costs c ON c.type_name = r.type_name AND c.mode = r.mode
               JOIN items i ON i.id = c.item_id
              WHERE r.type_name = ?
              ORDER BY r.mode, i.name',
            [$typeName]
        ) as $row) {
            $recipes[$row['mode']]['percent'] = (int) $row['percent'];
            $recipes[$row['mode']]['costs'][(string) $row['name']] = (int) $row['quantity'];
        }

        return $recipes;
    }

    /**
     * Replace a type's recipe in one mode; no percent or no cost removes it.
     * A gold recipe keeps only gold, a materials recipe everything but gold.
     * Unknown item names are skipped and returned.
     *
     * @param array<string, int> $costs item name => quantity
     * @return list<string> the item names the catalogue does not know
     */
    public function declare(string $typeName, string $mode, int $percent, array $costs): array
    {
        $costs = array_filter(
            $costs,
            static fn (int $quantity, string $name): bool => $quantity > 0 && ($name === 'or') === ($mode === self::GOLD),
            ARRAY_FILTER_USE_BOTH
        );
        $unknown = [];
        $this->conn->transactional(function () use ($typeName, $mode, $percent, $costs, &$unknown): void {
            $this->conn->executeStatement('DELETE FROM entity_type_repairs WHERE type_name = ? AND mode = ?', [$typeName, $mode]);
            if ($percent < 1 || $costs === []) {
                return;
            }
            $this->conn->insert('entity_type_repairs', ['type_name' => $typeName, 'mode' => $mode, 'percent' => min(100, $percent)]);
            foreach ($costs as $name => $quantity) {
                $itemId = $this->conn->fetchOne('SELECT id FROM items WHERE name = ?', [$name]);
                if ($itemId === false) {
                    $unknown[] = (string) $name;
                    continue;
                }
                $this->conn->insert('entity_type_repair_costs', ['type_name' => $typeName, 'mode' => $mode, 'item_id' => (int) $itemId, 'quantity' => $quantity]);
            }
        });

        return $unknown;
    }

    /**
     * Both recipes from a form or a bundle: [mode => [percent, costs]].
     *
     * @param array<string, mixed> $recipes
     * @return list<string> unknown item names
     */
    public function declareAll(string $typeName, array $recipes): array
    {
        $unknown = [];
        foreach (array_keys(self::MODES) as $mode) {
            $recipe = is_array($recipes[$mode] ?? null) ? $recipes[$mode] : [];
            $costs = is_array($recipe['costs'] ?? null) ? array_map('intval', $recipe['costs']) : [];
            array_push($unknown, ...$this->declare($typeName, $mode, (int) ($recipe['percent'] ?? 0), $costs));
        }

        return $unknown;
    }

    /** PV one dose restores: its share of the max, at least 1, never more than the damage. */
    public static function doseAmount(int $percent, int $maxPv, int $missing): int
    {
        return min($missing, max(1, (int) ceil($maxPv * $percent / 100)));
    }

    /**
     * What the bag or purse lacks to pay one dose.
     *
     * @param array<string, int> $costs
     * @return array<string, int> item name => quantity missing
     */
    public function missing(int $playerId, array $costs): array
    {
        $missing = [];
        foreach ($costs as $name => $quantity) {
            $owned = Item::get_item_by_name($name)->get_n($playerId, includeInstances: false);
            if ($owned < $quantity) {
                $missing[$name] = $quantity - $owned;
            }
        }

        return $missing;
    }

    /**
     * Restore $amount PV and charge one dose, in one transaction: a bag
     * emptied since the check rolls the repair back.
     *
     * @param array<string, int> $costs
     */
    public function mend(Player $payer, Player $target, int $amount, array $costs): void
    {
        $this->conn->transactional(function () use ($payer, $target, $amount, $costs): void {
            $target->putBonus(['pv' => $amount]);
            $this->pay($payer, $costs);
        });
    }

    /**
     * Takes each cost from the payer ('or' is gold); throws on the first one
     * short. Call inside a transaction.
     *
     * @param array<string, int> $costs
     */
    public function pay(Player $payer, array $costs): void
    {
        foreach ($costs as $name => $quantity) {
            $paid = $name === 'or'
                ? (new GoldService($this->conn))->spend((int) $payer->id, $quantity)
                : Item::get_item_by_name($name)->add_item($payer, -$quantity);
            if (!$paid) {
                throw new \RuntimeException('Il vous manque : ' . $this->label([$name => $quantity]) . '.');
            }
        }
    }

    /**
     * "1 × Pierre, 50 × Or".
     *
     * @param array<string, int> $costs
     */
    public function label(array $costs): string
    {
        $parts = [];
        foreach ($costs as $name => $quantity) {
            $parts[] = $quantity . ' × ' . ItemInstanceService::catalogLabel($this->conn, $name);
        }

        return implode(', ', $parts);
    }
}
