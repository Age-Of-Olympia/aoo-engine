<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Factory\PlayerFactory;
use App\Service\Map\EntityLocationService;
use Classes\Item;
use Doctrine\DBAL\Connection;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * The atelier's counter: repair a worn exemplar, recycle a broken one.
 *
 * Both price off the item's worth (RecipeWorthService). A full repair
 * (from the last hit point) costs a share of that worth, less wear costs
 * proportionally less. That bill is converted back into whole resources
 * of the recipe plus one resource of the object's race, or paid entirely
 * in gold with the artisan's margin on top. A broken exemplar is past
 * repair: recycling gives back a share of its resources and destroys it.
 */
final class RepairService
{
    /**
     * The three knobs, as percentages in admin_settings (admin/index.php):
     * share of the object's worth a repair from 1 PV costs, the artisan's
     * margin when the bill is paid in gold, share of the recipe a broken
     * exemplar gives back. Read on every quote, so a change applies at once.
     */
    public const SETTINGS = [
        'repair_full_share' => 25,
        'repair_gold_margin' => 150,
        'recycle_share' => 25,
    ];

    private Connection $conn;

    private AdminSettingsService $settings;

    private RecipeWorthService $worth;

    public function __construct()
    {
        $this->conn = EntityManagerFactory::getEntityManager()->getConnection();
        $this->settings = new AdminSettingsService();
        $this->worth = new RecipeWorthService($this->conn);
    }

    /** Recycling never gives back more than the recipe costs. */
    private const RECYCLE_SHARE_MAX = 100;

    /** A knob as a ratio: 25 → 0.25. Unset or invalid falls back to the default. */
    public function ratio(string $name): float
    {
        $stored = $this->settings->get($name, (string) self::SETTINGS[$name]);
        $percent = is_numeric($stored) && $stored >= 0 ? (float) $stored : self::SETTINGS[$name];
        if ($name === 'recycle_share') {
            $percent = min($percent, self::RECYCLE_SHARE_MAX);
        }

        return $percent / 100;
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
            $row['refund'] = $this->worth->shareOf($this->worth->flatten((string) $row['name']), $this->ratio('recycle_share'));
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * The bill for bringing one exemplar back to full life.
     *
     * The random draws are seeded by the exemplar, so the quote shown in
     * the list is the one charged at the click.
     *
     * @param array<string, mixed> $row an exemplar row (name, race, instance_id, durability, durability_max)
     * @return array{resources: array<string, int>, gold: int}|null null without a recipe
     */
    public function quote(array $row): ?array
    {
        $recipe = $this->worth->flatten((string) $row['name']);
        if ($recipe === []) {
            return null;
        }

        $max = max(1, (int) $row['durability_max']);
        $missing = (int) $row['durability_max'] - (int) $row['durability'];
        $bill = (int) ceil($this->worth->worthOf($recipe) * $this->ratio('repair_full_share') * $missing / $max);

        $dice = new Randomizer(new Mt19937((int) $row['instance_id']));
        $resources = $this->worth->resourcesWorth($bill, $recipe, $dice);

        $racial = $this->worth->racialResource((string) $row['race'], $recipe, $dice);
        if ($racial !== null) {
            $resources[$racial['name']] = ($resources[$racial['name']] ?? 0) + 1;
            $bill += $racial['price'];
        }

        return [
            'resources' => $resources,
            'gold' => (int) ceil($bill * $this->ratio('repair_gold_margin')),
        ];
    }

    public function repairWithResources(int $playerId, int $instanceId): void
    {
        $row = $this->heldRepairable($playerId, $instanceId);
        $quote = $this->quote($row);
        if ($quote === null) {
            throw new \RuntimeException('Sans recette connue, cet objet ne se répare pas.');
        }

        $player = PlayerFactory::legacy($playerId);
        $this->conn->transactional(function () use ($row, $quote, $player): void {
            $this->restore((int) $row['entity_id']);
            foreach ($quote['resources'] as $name => $count) {
                if (!Item::get_item_by_name($name)->add_item($player, -$count)) {
                    throw new \RuntimeException("Il vous manque : {$name} ({$count}).");
                }
            }
        });
    }

    public function repairWithGold(int $playerId, int $instanceId): void
    {
        $row = $this->heldRepairable($playerId, $instanceId);
        $quote = $this->quote($row);
        if ($quote === null) {
            throw new \RuntimeException('Sans recette connue, cet objet ne se répare pas.');
        }

        $this->conn->transactional(function () use ($row, $quote, $playerId): void {
            $this->restore((int) $row['entity_id']);
            if (!(new GoldService($this->conn))->spend($playerId, $quote['gold'])) {
                throw new \RuntimeException('Pas assez d\'or.');
            }
        });
    }

    /** A broken exemplar becomes a share of its ingredients, and is gone. */
    public function recycle(int $playerId, int $instanceId): void
    {
        $row = $this->held($playerId, $instanceId);
        if (!ItemInstanceService::isBroken((int) $row['durability'])) {
            throw new \RuntimeException('Seul un objet brisé se recycle.');
        }

        $refund = $this->worth->shareOf($this->worth->flatten((string) $row['name']), $this->ratio('recycle_share'));

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

        /* Wreck and refund in one transaction (the legacy Db shares the
         * connection; same wreck steps as PlacedExemplarService). Only the
         * request that flips `destroyed` pays out: a double click finds the
         * wreck already gone. */
        $entityId = (int) $row['entity_id'];
        $player = PlayerFactory::legacy($playerId);
        $this->conn->transactional(function () use ($row, $entityId, $refund, $player): void {
            $destroyed = $this->conn->executeStatement(
                'UPDATE item_instances SET destroyed = 1 WHERE id = ? AND destroyed = 0',
                [(int) $row['instance_id']]
            );
            if ($destroyed === 0) {
                throw new \RuntimeException('Cet objet n\'est pas dans votre sac.');
            }
            foreach (['players_bonus', 'players_effects', 'players_items'] as $table) {
                $this->conn->executeStatement("DELETE FROM {$table} WHERE player_id = ?", [$entityId]);
            }
            (new EntityLocationService($this->conn))->shelve($entityId);

            foreach ($refund as $name => $count) {
                Item::get_item_by_name($name)->add_item($player, $count);
            }
        });
    }

    /**
     * Full life: the wear deficit disappears. Called before the charge, in
     * its transaction: of two simultaneous repairs, the second deletes
     * nothing and its charge rolls back.
     */
    private function restore(int $entityId): void
    {
        $restored = $this->conn->executeStatement(
            "DELETE FROM players_bonus WHERE player_id = ? AND name = 'pv'",
            [$entityId]
        );
        if ($restored === 0) {
            throw new \RuntimeException('Cet objet est intact.');
        }
    }

    /** @return array<int, array<string, mixed>> the exemplars in the bag, with their wear */
    private function heldExemplars(int $playerId): array
    {
        return $this->conn->fetchAllAssociative(
            'SELECT it.name, it.race, ' . ItemInstanceService::DISPLAY_NAME . ' AS label, i.item_id, i.id AS instance_id,
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
