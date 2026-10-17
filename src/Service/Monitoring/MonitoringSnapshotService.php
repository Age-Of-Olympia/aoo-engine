<?php

namespace App\Service\Monitoring;

use App\Enum\EntityCategory;
use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * Game measures, live or as the daily snapshot the cron stores — the same
 * measure both ways, so a live value and a stored one always compare.
 *
 * @phpstan-type Metrics array{
 *     gold: array{characters: int, structures: int, banks: int, ground: int},
 *     items: int,
 *     xp: int,
 *     players: array<int, array{name: string, xp: int, gold: int, active?: bool}>,
 *     admins: list<int>,
 *     tables: array<string, int>
 * }
 */
final class MonitoringSnapshotService
{
    public const ADMIN_OPTIONS = ['isAdmin', 'isSuperAdmin'];

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** Store today's measure, replacing an earlier one of the same day. */
    public function take(): void
    {
        $this->conn->executeStatement(
            'REPLACE INTO monitoring_snapshots (day, taken_at, metrics) VALUES (CURDATE(), NOW(), ?)',
            [json_encode($this->measure(), JSON_THROW_ON_ERROR)]
        );
    }

    /**
     * Stored snapshots, newest first.
     *
     * @return list<array{day: string, taken_at: string, metrics: Metrics}>
     */
    public function history(int $days): array
    {
        $rows = $this->conn->fetchAllAssociative(
            'SELECT day, taken_at, metrics FROM monitoring_snapshots ORDER BY day DESC LIMIT ' . max(1, $days)
        );

        return array_map(static fn (array $row): array => [
            'day'      => (string) $row['day'],
            'taken_at' => (string) $row['taken_at'],
            'metrics'  => json_decode((string) $row['metrics'], true, 512, JSON_THROW_ON_ERROR),
        ], $rows);
    }

    /**
     * The biggest tables, largest first.
     *
     * @return array<string, array{bytes: int, rows: int}>
     */
    public function tableSizes(int $limit): array
    {
        $tables = [];
        foreach ($this->conn->fetchAllAssociative(
            'SELECT table_name AS name, table_rows AS row_count, data_length + index_length AS bytes FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_type = \'BASE TABLE\' ORDER BY bytes DESC LIMIT ' . max(1, $limit)
        ) as $row) {
            $tables[(string) $row['name']] = ['bytes' => (int) $row['bytes'], 'rows' => (int) $row['row_count']];
        }

        return $tables;
    }

    /** @return Metrics */
    public function measure(): array
    {
        $characters = EntityCategory::addressableSql();
        $gold = (int) $this->conn->fetchOne("SELECT id FROM items WHERE name = 'or'");

        $bags = $this->conn->fetchAssociative(
            "SELECT COALESCE(SUM(CASE WHEN p.player_type IN ({$characters}) THEN pi.n END), 0) AS characters,
                    COALESCE(SUM(CASE WHEN p.player_type NOT IN ({$characters}) THEN pi.n END), 0) AS structures
               FROM players_items pi JOIN players p ON p.id = pi.player_id
              WHERE pi.item_id = ?",
            [$gold]
        ) ?: ['characters' => 0, 'structures' => 0];

        $players = [];
        foreach ($this->conn->fetchAllAssociative(
            "SELECT p.id, p.name, p.xp,
                    COALESCE((SELECT SUM(n) FROM players_items WHERE player_id = p.id AND item_id = ?), 0)
                  + COALESCE((SELECT SUM(n) FROM players_items_bank WHERE player_id = p.id AND item_id = ?), 0) AS gold,
                    COALESCE(NULLIF(a.last_login_time, 0), p.lastLoginTime) >= ? AS active
               FROM players p LEFT JOIN accounts a ON a.player_id = p.id WHERE p.player_type = 'real'",
            [$gold, $gold, time() - INACTIVE_TIME]
        ) as $row) {
            $players[(int) $row['id']] = [
                'name'   => (string) $row['name'],
                'xp'     => (int) $row['xp'],
                'gold'   => (int) $row['gold'],
                'active' => (bool) $row['active'],
            ];
        }

        return [
            'gold' => [
                'characters' => (int) $bags['characters'],
                'structures' => (int) $bags['structures'],
                'banks'      => (int) $this->conn->fetchOne('SELECT COALESCE(SUM(n), 0) FROM players_items_bank WHERE item_id = ?', [$gold]),
                'ground'     => (int) $this->conn->fetchOne('SELECT COALESCE(SUM(n), 0) FROM map_items WHERE item_id = ?', [$gold]),
            ],
            'items' => (int) $this->conn->fetchOne(
                'SELECT (SELECT COALESCE(SUM(n), 0) FROM players_items) + (SELECT COALESCE(SUM(n), 0) FROM players_items_bank)
                      + (SELECT COALESCE(SUM(n), 0) FROM map_items)'
            ),
            'xp' => (int) $this->conn->fetchOne("SELECT COALESCE(SUM(xp), 0) FROM players WHERE player_type = 'real'"),
            'players' => $players,
            'admins' => array_map('intval', $this->conn->fetchFirstColumn(
                'SELECT DISTINCT player_id FROM players_options WHERE name IN (?, ?) ORDER BY player_id',
                self::ADMIN_OPTIONS
            )),
            'tables' => array_map(static fn (array $t): int => $t['bytes'], $this->tableSizes(15)),
        ];
    }
}
