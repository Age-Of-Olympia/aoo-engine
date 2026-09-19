<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * The effects an item lands when its bearer strikes: one row per effect,
 * with the outcome that triggers it (hit or miss) and who receives it
 * (the bearer, the target, or the target and the eight cells around it).
 */
class ItemEffectService
{
    public const OUTCOMES = ['hit', 'miss'];
    public const TARGETS = ['self', 'target', 'area'];

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** @return list<object{name: string, duration: int, outcome: string, target: string}> */
    public function listForItem(int $itemId): array
    {
        return $this->listForItems([$itemId]);
    }

    /**
     * @param list<int> $itemIds
     * @return list<object{name: string, duration: int, outcome: string, target: string}>
     */
    public function listForItems(array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
        if ($itemIds === []) {
            return [];
        }

        $rows = $this->conn->fetchAllAssociative(
            'SELECT effect AS name, duration, outcome, target FROM item_effects
              WHERE item_id IN (' . implode(',', array_fill(0, count($itemIds), '?')) . ')
              ORDER BY id',
            $itemIds
        );

        return array_map(static function (array $row): object {
            $row['duration'] = (int) $row['duration'];

            return (object) $row;
        }, $rows);
    }

    /**
     * Replace the item's rows with the given ones.
     *
     * @param list<array{name: string, duration: int, outcome: string, target: string}> $rows
     * @throws \InvalidArgumentException unknown effect, outcome or target
     */
    public function replaceForItem(int $itemId, array $rows): void
    {
        $effects = new EffectService();
        foreach ($rows as $row) {
            if (!$effects->exists($row['name'])) {
                throw new \InvalidArgumentException("Effet inconnu du catalogue : « {$row['name']} ».");
            }
            if (!in_array($row['outcome'], self::OUTCOMES, true) || !in_array($row['target'], self::TARGETS, true)) {
                throw new \InvalidArgumentException("Déclencheur ou cible invalide pour « {$row['name']} ».");
            }
        }

        $this->conn->transactional(function (Connection $conn) use ($itemId, $rows): void {
            $conn->executeStatement('DELETE FROM item_effects WHERE item_id = ?', [$itemId]);
            foreach ($rows as $row) {
                $conn->executeStatement(
                    'INSERT INTO item_effects (item_id, effect, duration, outcome, target) VALUES (?, ?, ?, ?, ?)',
                    [$itemId, $row['name'], (int) $row['duration'], $row['outcome'], $row['target']]
                );
            }
        });
    }
}
