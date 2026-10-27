<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;

/** Monthly reward distribution for the leading cult in the faith ranking. */
final class FaithRewardDistributionService
{
    private const LAST_DISTRIBUTION_SETTING = 'faith_last_distribution_month';

    private Connection $connection;

    public function __construct()
    {
        $this->connection = EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** @return array{distributed:int, god_id:?int, followers:int, skipped:bool, details:list<array{player:string,player_id:int,item:string,item_id:int,quantity:int}>} */
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
                return ['distributed' => 0, 'god_id' => null, 'followers' => 0, 'skipped' => true, 'details' => []];
            }

            $count = (new FaithRewardService())->monthlyRewardCount();
            $rewards = $connection->fetchAllAssociative(
                "SELECT i.id AS item_id, COALESCE(NULLIF(i.label, ''), i.name) AS item_name, r.quantity
                   FROM faith_reward_items r
                   JOIN items i ON i.id = r.item_id
                  ORDER BY i.id"
            );
            $godId = $this->winningGodId();
            $followers = $godId === null ? [] : array_map('intval', $connection->fetchFirstColumn(
                "SELECT id FROM players
                  WHERE godId = ? AND id > 0
                    AND player_type IN ('real', 'npc', 'tutorial')
                  ORDER BY id",
                [$godId]
            ));

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
                        'INSERT INTO players_items (player_id, item_id, n) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE n = n + VALUES(n)',
                        [$playerId, (int) $reward['item_id'], (int) $reward['quantity']]
                    );
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
                'followers' => count($followers),
                'skipped' => false,
                'details' => $details,
            ];
        });
    }

    private function winningGodId(): ?int
    {
        $plans = array_keys((new PlanService())->forSeason());
        if ($plans === []) {
            return null;
        }
        $marks = implode(', ', array_fill(0, count($plans), '?'));
        $activeSince = time() - INACTIVE_TIME;
        $row = $this->connection->fetchAssociative(
            "SELECT g.id, SUM(f.pf) AS total_faith
               FROM players g
               JOIN players f ON f.godId = g.id AND f.id > 0
                 AND f.coords_id IN (SELECT c.id FROM coords c WHERE c.plan IN ({$marks}))
               JOIN accounts acc ON acc.player_id = f.id
                 AND acc.last_login_time >= ?
              WHERE g.race = 'dieu'
                AND EXISTS (SELECT 1 FROM players a WHERE a.race = 'altar' AND a.godId = g.id)
              GROUP BY g.id
             HAVING total_faith > 0
              ORDER BY total_faith DESC, g.id ASC
              LIMIT 1",
            [...$plans, $activeSince]
        );

        return $row === false ? null : (int) $row['id'];
    }
}
