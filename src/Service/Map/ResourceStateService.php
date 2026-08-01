<?php

namespace App\Service\Map;

use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * A resource entity's own state: dry, or standing.
 *
 * The `map_resources` equivalent is `damages` — -1 harvestable, -2 exhausted.
 * An entity has no `damages`, so the state moves to its satellite, and an
 * absent row means standing: a resource is harvestable until something says
 * otherwise.
 */
final class ResourceStateService
{
    private ?Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn;
    }

    private function conn(): Connection
    {
        return $this->conn ??= EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** @param list<int> $entityIds */
    public function exhaust(array $entityIds): int
    {
        return $this->write($entityIds, true);
    }

    /** @param list<int> $entityIds */
    public function regrow(array $entityIds): int
    {
        return $this->write($entityIds, false);
    }

    public function isExhausted(int $entityId): bool
    {
        return $this->conn()->fetchOne(
            'SELECT 1 FROM resources WHERE player_id = ? AND exhausted_at IS NOT NULL',
            [$entityId]
        ) !== false;
    }

    /**
     * The exhausted ones among those given, so a caller can filter a whole
     * neighbourhood in one query instead of one per cell.
     *
     * @param list<int> $entityIds
     * @return array<int, true>
     */
    public function exhaustedAmong(array $entityIds): array
    {
        if ($entityIds === []) {
            return [];
        }

        $in = implode(',', array_map('intval', $entityIds));
        $dry = [];

        foreach ($this->conn()->fetchFirstColumn(
            "SELECT player_id FROM resources WHERE exhausted_at IS NOT NULL AND player_id IN ({$in})"
        ) as $id) {
            $dry[(int) $id] = true;
        }

        return $dry;
    }

    /** @param list<int> $entityIds */
    private function write(array $entityIds, bool $exhausted): int
    {
        $written = 0;

        foreach ($entityIds as $id) {
            $written += $this->conn()->executeStatement(
                'INSERT INTO resources (player_id, exhausted_at) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE exhausted_at = VALUES(exhausted_at)',
                [(int) $id, $exhausted ? date('Y-m-d H:i:s') : null]
            );
        }

        return $written;
    }
}
