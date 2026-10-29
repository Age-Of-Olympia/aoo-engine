<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;

/** Monthly reward distribution for the god controlling the most plans. */
final class FaithRewardDistributionService
{
    private const LAST_DISTRIBUTION_SETTING = 'faith_last_distribution_month';

    private Connection $connection;

    public function __construct()
    {
        $this->connection = EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** @return array{distributed:int, god_id:?int, god_name:?string, followers:int, skipped:bool, details:list<array{player:string,player_id:int,item:string,item_id:int,quantity:int}>} */
    public function distributeMonthly(): array
    {
        $month = (new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))->format('Y-m');

        return $this->connection->transactional(function (Connection $connection) use ($month): array {
            // Lock one persistent marker so overlapping/retried cron runs cannot pay twice.
            $connection->executeStatement(
                'INSERT IGNORE INTO admin_settings (name, value) VALUES (?, ?)',
                [self::LAST_DISTRIBUTION_SETTING, '']
            );
            $lastMonth = (string) $connection->fetchOne(
                'SELECT value FROM admin_settings WHERE name = ? FOR UPDATE',
                [self::LAST_DISTRIBUTION_SETTING]
            );
            if ($lastMonth === $month) {
                $details = $this->distributionDetails($month);
                $godId = $details === [] ? null : $details[0]['god_id'];
                return [
                    'distributed' => count($details),
                    'god_id' => $godId,
                    'god_name' => $godId === null ? null : (string) $connection->fetchOne(
                        'SELECT name FROM players WHERE id = ?',
                        [$godId]
                    ),
                    'followers' => count(array_unique(array_column($details, 'player_id'))),
                    'skipped' => true,
                    'details' => array_map(static function (array $detail): array {
                        unset($detail['god_id']);
                        return $detail;
                    }, $details),
                ];
            }

            $count = (new FaithRewardService())->monthlyRewardCount();
            $rewards = $connection->fetchAllAssociative(
                "SELECT i.id AS item_id, COALESCE(NULLIF(i.label, ''), i.name) AS item_name, r.quantity
                   FROM faith_reward_items r
                   JOIN items i ON i.id = r.item_id
                  ORDER BY i.id"
            );
            $godId = $this->winningGodId();
            $plans = array_keys((new PlanService())->forSeason());
            $followers = [];
            if ($godId !== null && $plans !== []) {
                $planMarks = implode(', ', array_fill(0, count($plans), '?'));
                $followers = array_map('intval', $connection->fetchFirstColumn(
                    "SELECT p.id
                       FROM players p
                       JOIN accounts acc ON acc.player_id = p.id AND acc.last_login_time >= ?
                       JOIN coords c ON c.id = p.coords_id AND c.plan IN ({$planMarks})
                      WHERE p.godId = ? AND p.id > 0
                        AND p.player_type IN ('real', 'npc', 'tutorial')
                      ORDER BY p.id",
                    [time() - INACTIVE_TIME, ...$plans, $godId]
                ));
            }
            $godName = $godId === null ? null : (string) $connection->fetchOne(
                'SELECT name FROM players WHERE id = ?',
                [$godId]
            );

            $distributed = 0;
            $details = [];
            if ($count > 0 && $rewards !== [] && $followers !== []) {
                // Each shuffled round gives one reward to every follower before
                // anyone can be selected again. A partial final round is shuffled too.
                $round = [];
                for ($i = 0; $i < $count; $i++) {
                    if ($round === []) {
                        $round = $followers;
                        shuffle($round);
                    }
                    $playerId = array_pop($round);
                    $reward = $rewards[random_int(0, count($rewards) - 1)];
                    $playerName = (string) $connection->fetchOne('SELECT name FROM players WHERE id = ?', [$playerId]);
                    $connection->executeStatement(
                        'INSERT INTO players_items_bank (player_id, item_id, n) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE n = n + VALUES(n)',
                        [$playerId, (int) $reward['item_id'], (int) $reward['quantity']]
                    );
                    $connection->insert('faith_reward_distributions', [
                        'distribution_month' => $month,
                        'god_id' => $godId,
                        'player_id' => $playerId,
                        'player_name' => $playerName,
                        'item_id' => (int) $reward['item_id'],
                        'item_name' => (string) $reward['item_name'],
                        'quantity' => (int) $reward['quantity'],
                    ]);
                    $distributed++;
                    $details[] = [
                        'player' => $playerName,
                        'player_id' => $playerId,
                        'item' => (string) $reward['item_name'],
                        'item_id' => (int) $reward['item_id'],
                        'quantity' => (int) $reward['quantity'],
                    ];
                }
            }

            $connection->update('admin_settings', ['value' => $month], ['name' => self::LAST_DISTRIBUTION_SETTING]);

            return [
                'distributed' => $distributed,
                'god_id' => $godId,
                'god_name' => $godName,
                'followers' => count($followers),
                'skipped' => false,
                'details' => $details,
            ];
        });
    }

    /** @return list<array{god_id:int,player:string,player_id:int,item:string,item_id:int,quantity:int}> */
    private function distributionDetails(string $month): array
    {
        return array_map(static fn (array $row): array => [
            'god_id' => (int) $row['god_id'],
            'player' => (string) $row['player_name'],
            'player_id' => (int) $row['player_id'],
            'item' => (string) $row['item_name'],
            'item_id' => (int) $row['item_id'],
            'quantity' => (int) $row['quantity'],
        ], $this->connection->fetchAllAssociative(
            'SELECT god_id, player_name, player_id, item_name, item_id, quantity
               FROM faith_reward_distributions
              WHERE distribution_month = ?
              ORDER BY id',
            [$month]
        ));
    }

    private function winningGodId(): ?int
    {
        $plans = array_keys((new PlanService())->forSeason());
        if ($plans === []) {
            return null;
        }
        $marks = implode(', ', array_fill(0, count($plans), '?'));
        $activeSince = time() - INACTIVE_TIME;
        $gods = $this->connection->fetchAllAssociative(
            "SELECT g.id, COALESCE(controlled.total_plans, 0) AS total_plans
               FROM players g
               JOIN players f ON f.godId = g.id AND f.id > 0
                 AND f.coords_id IN (SELECT c.id FROM coords c WHERE c.plan IN ({$marks}))
               JOIN accounts acc ON acc.player_id = f.id
                 AND acc.last_login_time >= ?
               LEFT JOIN (
                    SELECT a.godId, COUNT(DISTINCT ec.plan) AS total_plans
                      FROM players a
                      JOIN entity_cells ec ON ec.player_id = a.id
                     WHERE a.race = 'altar' AND a.godId != 0
                       AND ec.plan IN ({$marks})
                       AND ec.plan IN (
                            SELECT ec2.plan
                              FROM players a2
                              JOIN entity_cells ec2 ON ec2.player_id = a2.id
                             WHERE a2.race = 'altar' AND a2.godId != 0
                               AND ec2.plan IN ({$marks})
                             GROUP BY ec2.plan
                            HAVING COUNT(DISTINCT a2.godId) = 1
                       )
                     GROUP BY a.godId
               ) controlled ON controlled.godId = g.id
              WHERE g.race = 'dieu'
                AND EXISTS (SELECT 1 FROM players a WHERE a.race = 'altar' AND a.godId = g.id)
              GROUP BY g.id, controlled.total_plans
             HAVING SUM(f.pf) > 0
              ORDER BY total_plans DESC, g.id ASC",
            [...$plans, $activeSince, ...$plans, ...$plans]
        );

        if ($gods === [] || (int) $gods[0]['total_plans'] < 1) {
            return null;
        }

        if (isset($gods[1]) && (int) $gods[1]['total_plans'] === (int) $gods[0]['total_plans']) {
            return null;
        }

        return (int) $gods[0]['id'];
    }
}
