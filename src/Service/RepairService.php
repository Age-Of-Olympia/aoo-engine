<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Factory\PlayerFactory;
use App\Service\Map\EntityLocationService;
use Classes\Item;
use Doctrine\DBAL\Connection;

/**
 * The atelier's counter: repair a worn exemplar, recycle a broken one.
 *
 * Both price off the item's RECIPE. A full repair (from the last hit
 * point) costs a share of the recipe; less wear costs proportionally
 * less. The bill is paid either in the recipe's resources plus labour, or
 * entirely in gold — the artisan buys the resources with a margin, so gold
 * always costs more. A broken exemplar is past repair: recycling gives
 * back a share of its ingredients and destroys it.
 */
final class RepairService
{
    /**
     * The four knobs, as percentages in admin_settings (admin/index.php):
     * share of the recipe a repair from 1 PV costs, labour as a share of
     * the resources' value (at least 1 PO), the artisan's margin on
     * resources paid in gold, share of the recipe a broken exemplar gives
     * back. Read on every quote, so a change applies at once.
     */
    public const SETTINGS = [
        'repair_full_share' => 25,
        'repair_labour_share' => 10,
        'repair_gold_margin' => 150,
        'recycle_share' => 25,
    ];

    private Connection $conn;

    private AdminSettingsService $settings;

    public function __construct()
    {
        $this->conn = EntityManagerFactory::getEntityManager()->getConnection();
        $this->settings = new AdminSettingsService();
    }

    /** A knob as a ratio: 25 → 0.25. Unset or invalid falls back to the default. */
    public function ratio(string $name): float
    {
        $stored = $this->settings->get($name, (string) self::SETTINGS[$name]);

        return (is_numeric($stored) && $stored >= 0 ? (float) $stored : self::SETTINGS[$name]) / 100;
    }

    /**
     * The worn exemplars in the bag (not broken, not intact), each with
     * its quote; a row without a recipe carries no quote and no button.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listRepairable(int $playerId): array
    {
        $rows = [];
        foreach ($this->heldExemplars($playerId) as $row) {
            if ((int) $row['durability'] >= (int) $row['durability_max'] || ItemInstanceService::isBroken((int) $row['durability'])) {
                continue;
            }
            $row['quote'] = $this->quote($row);
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> the broken exemplars in the bag, with what they give back */
    public function listBroken(int $playerId): array
    {
        $rows = [];
        foreach ($this->heldExemplars($playerId) as $row) {
            if (!ItemInstanceService::isBroken((int) $row['durability'])) {
                continue;
            }
            $row['refund'] = $this->shareOf($this->recipeOf((string) $row['name']), $this->ratio('recycle_share'), roundUp: false);
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * The bill for bringing one exemplar back to full life.
     *
     * @param array<string, mixed> $row an exemplar row (name, durability, durability_max)
     * @return array{resources: array<string, int>, labour: int, gold: int}|null null without a recipe
     */
    public function quote(array $row): ?array
    {
        $recipe = $this->recipeOf((string) $row['name']);
        if ($recipe === []) {
            return null;
        }

        $max = max(1, (int) $row['durability_max']);
        $share = $this->ratio('repair_full_share') * ((int) $row['durability_max'] - (int) $row['durability']) / $max;

        $resources = $this->shareOf($recipe, $share, roundUp: true);
        $value = 0;
        foreach ($recipe as $ingredient) {
            $value += $ingredient['price'] * $ingredient['count'] * $share;
        }
        $labour = max(1, (int) ceil($value * $this->ratio('repair_labour_share')));

        return [
            'resources' => $resources,
            'labour' => $labour,
            'gold' => (int) ceil($value * $this->ratio('repair_gold_margin')) + $labour,
        ];
    }

    public function repairWithResources(int $playerId, int $instanceId): void
    {
        $row = $this->heldRepairable($playerId, $instanceId);
        $quote = $this->quote($row);
        if ($quote === null) {
            throw new \RuntimeException('Sans recette connue, cet objet ne se répare pas.');
        }

        /* Two connections (gold on DBAL, stacks on the legacy mysqli one), so
         * no single transaction covers the bill: labour first, atomic on its
         * own; then the resources in the legacy transaction, refunding the
         * labour if they fall short. ponytail: one connection would make this
         * one transaction — when Item::add_item moves to DBAL. */
        if (!(new GoldService($this->conn))->spend($playerId, $quote['labour'])) {
            throw new \RuntimeException('Pas assez d\'or pour la main-d\'œuvre.');
        }

        $player = PlayerFactory::legacy($playerId);
        $db = new \Classes\Db();
        $db->beginTransaction();
        foreach ($quote['resources'] as $name => $count) {
            if (!Item::get_item_by_name($name)->add_item($player, -$count)) {
                $db->rollback();
                Item::get_item_by_name('or')->add_item($player, $quote['labour']);
                throw new \RuntimeException("Il vous manque : {$name} ({$count}).");
            }
        }
        $db->commit();

        $this->restore((int) $row['entity_id']);
    }

    public function repairWithGold(int $playerId, int $instanceId): void
    {
        $row = $this->heldRepairable($playerId, $instanceId);
        $quote = $this->quote($row);
        if ($quote === null) {
            throw new \RuntimeException('Sans recette connue, cet objet ne se répare pas.');
        }

        if (!(new GoldService($this->conn))->spend($playerId, $quote['gold'])) {
            throw new \RuntimeException('Pas assez d\'or.');
        }
        $this->restore((int) $row['entity_id']);
    }

    /** A broken exemplar becomes a share of its ingredients, and is gone. */
    public function recycle(int $playerId, int $instanceId): void
    {
        $row = $this->held($playerId, $instanceId);
        if (!ItemInstanceService::isBroken((int) $row['durability'])) {
            throw new \RuntimeException('Seul un objet brisé se recycle.');
        }

        $refund = $this->shareOf($this->recipeOf((string) $row['name']), $this->ratio('recycle_share'), roundUp: false);

        /* The bag-lines rule: the wreck frees its line, each new stack takes one. */
        $capacity = new ContainerService();
        $newLines = 0;
        foreach (array_keys($refund) as $name) {
            if ($capacity->stackNeedsRoom($playerId, (int) Item::get_item_by_name($name)->id)) {
                $newLines++;
            }
        }
        $max = $capacity->capacityOf($playerId);
        if ($max !== null && $capacity->lineCountOf($playerId) - 1 + $newLines > $max) {
            throw new \RuntimeException('Votre sac est plein.');
        }

        /* The wreck goes first, in one transaction (same steps as a vanished
         * exemplar, PlacedExemplarService); the refund follows on the legacy
         * connection. */
        $entityId = (int) $row['entity_id'];
        $this->conn->transactional(function () use ($row, $entityId): void {
            $this->conn->executeStatement('UPDATE item_instances SET destroyed = 1 WHERE id = ?', [(int) $row['instance_id']]);
            foreach (['players_bonus', 'players_effects', 'players_items'] as $table) {
                $this->conn->executeStatement("DELETE FROM {$table} WHERE player_id = ?", [$entityId]);
            }
            (new EntityLocationService($this->conn))->shelve($entityId);
        });

        $player = PlayerFactory::legacy($playerId);
        foreach ($refund as $name => $count) {
            Item::get_item_by_name($name)->add_item($player, $count);
        }
    }

    /** Full life: the wear deficit disappears. */
    private function restore(int $entityId): void
    {
        $this->conn->executeStatement(
            "DELETE FROM players_bonus WHERE player_id = ? AND name = 'pv'",
            [$entityId]
        );
    }

    /**
     * The recipe's ingredients priced from the catalog: name => [count, price].
     *
     * @return array<string, array{count: int, price: int}>
     */
    private function recipeOf(string $itemName): array
    {
        $ingredients = (new RecipeService())->ingredientsForResult($itemName);
        if ($ingredients === []) {
            return [];
        }

        $prices = $this->conn->fetchAllKeyValue(
            'SELECT name, price FROM items WHERE name IN (?)',
            [array_keys($ingredients)],
            [\Doctrine\DBAL\ArrayParameterType::STRING]
        );

        $recipe = [];
        foreach ($ingredients as $name => $count) {
            $recipe[$name] = ['count' => (int) $count, 'price' => (int) ($prices[$name] ?? 0)];
        }

        return $recipe;
    }

    /**
     * A share of a recipe, whole units only: repair rounds up (the artisan
     * does not cut a plank), recycling rounds down (nothing is conjured).
     *
     * @param array<string, array{count: int, price: int}> $recipe
     * @return array<string, int>
     */
    private function shareOf(array $recipe, float $share, bool $roundUp): array
    {
        $out = [];
        foreach ($recipe as $name => $ingredient) {
            $n = (int) ($roundUp ? ceil($ingredient['count'] * $share) : floor($ingredient['count'] * $share));
            if ($n > 0) {
                $out[$name] = $n;
            }
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>> the exemplars in the bag, with their wear */
    private function heldExemplars(int $playerId): array
    {
        return $this->conn->fetchAllAssociative(
            'SELECT it.name, ' . ItemInstanceService::DISPLAY_NAME . ' AS label, i.item_id, i.id AS instance_id,
                    i.custom_name, e.id AS entity_id, ' . ItemInstanceService::WEAR_SELECT . '
               FROM players e
               JOIN item_instances i ON i.entity_id = e.id
               JOIN items it ON it.id = i.item_id
               ' . ItemInstanceService::WEAR_JOIN . "
              WHERE e.holder_id = ? AND e.slot = '' AND i.destroyed = 0
              ORDER BY it.name, i.id",
            [$playerId]
        );
    }

    /** @return array<string, mixed> */
    private function held(int $playerId, int $instanceId): array
    {
        foreach ($this->heldExemplars($playerId) as $row) {
            if ((int) $row['instance_id'] === $instanceId) {
                return $row;
            }
        }

        throw new \RuntimeException('Cet objet n\'est pas dans votre sac.');
    }

    /** @return array<string, mixed> */
    private function heldRepairable(int $playerId, int $instanceId): array
    {
        $row = $this->held($playerId, $instanceId);
        if (ItemInstanceService::isBroken((int) $row['durability'])) {
            throw new \RuntimeException('Brisé, cet objet ne se répare plus.');
        }
        if ((int) $row['durability'] >= (int) $row['durability_max']) {
            throw new \RuntimeException('Cet objet est intact.');
        }

        return $row;
    }
}
