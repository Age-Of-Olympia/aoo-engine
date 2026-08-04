<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * The faction's JOURNAL — the players_logs idea, for the house: who
 * took what from which chest, who turned which lock, who took a
 * building's commands. The message is stored whole, names resolved at
 * the gesture, and the page shows the house what happened while it
 * slept — internal theft included: that is the point.
 */
final class FactionLogService extends BaseService
{
    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        parent::__construct();
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** Writes one line; a blank or unknown faction writes nothing. */
    public function add(string $factionCode, ?int $actorId, string $message): void
    {
        $factionCode = strtolower(trim($factionCode));
        if ($factionCode === '' || $message === '') {
            return;
        }

        $factionId = $this->conn->fetchOne(
            'SELECT id FROM factions WHERE CONVERT(code USING utf8mb4) = CONVERT(? USING utf8mb4)',
            [$factionCode]
        );
        if ($factionId === false) {
            return;
        }

        $this->conn->executeStatement(
            'INSERT INTO faction_logs (faction_id, player_id, message, time) VALUES (?, ?, ?, ?)',
            [(int) $factionId, $actorId, $message, time()]
        );
    }

    /**
     * The latest lines, newest first.
     *
     * @return array<int, array{time: int, message: string}>
     */
    public function listOf(string $factionCode, int $limit = 30): array
    {
        $rows = $this->conn->fetchAllAssociative(
            'SELECT l.time, l.message
               FROM faction_logs l
               JOIN factions f ON f.id = l.faction_id
              WHERE CONVERT(f.code USING utf8mb4) = CONVERT(? USING utf8mb4)
              ORDER BY l.id DESC
              LIMIT ' . max(1, $limit),
            [strtolower(trim($factionCode))]
        );

        return array_map(static fn (array $row): array => [
            'time' => (int) $row['time'],
            'message' => (string) $row['message'],
        ], $rows);
    }
}
