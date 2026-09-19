<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * The effects an item lands when its bearer strikes: one row per effect,
 * with the outcome that triggers it (hit or miss) and who receives it
 * (the bearer or the target).
 */
class ItemEffectService
{
    public const OUTCOMES = ['hit', 'miss'];
    public const TARGETS = ['self', 'target'];

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
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
            'SELECT item_id, effect AS name, duration, outcome, target FROM item_effects
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
     * The same rows grouped by item, for a list rendered in one query.
     *
     * @param list<int> $itemIds
     * @return array<int, list<object{name: string, duration: int, outcome: string, target: string}>>
     */
    public function mapForItems(array $itemIds): array
    {
        $map = [];
        foreach ($this->listForItems($itemIds) as $row) {
            $map[(int) $row->item_id][] = $row;
        }

        return $map;
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
