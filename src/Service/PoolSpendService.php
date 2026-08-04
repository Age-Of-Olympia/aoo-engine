<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Interface\ActorInterface;
use App\Simulation\SimulationGuard;
use Doctrine\DBAL\Connection;

/**
 * The one writer that SPENDS an entity's turn pool (A, MVT, PM, AE).
 *
 * A pool is shared the moment several hands can drive the same entity —
 * faction members on one building, two tabs on one character. Spending
 * therefore never reads then writes: one guarded UPDATE floored at the
 * empty pool, and the answer is what was actually taken, never more
 * than the pool held. Deltas that are not spends — wounds, heals,
 * malus, buffs — keep going through {@see \Classes\Player::putBonus()},
 * whose branch behaviours (bleeding, healing caps) belong to them.
 *
 * The pool of a trait is `caracs` (the per-turn maximum, computed from
 * race, equipment and effects) plus the `players_bonus` row (negative =
 * spent, positive = a buff beyond the maximum).
 */
final class PoolSpendService extends BaseService
{
    /** The per-turn pool; spending any other trait is not this service's job. */
    public const POOL_TRAITS = ['a', 'mvt', 'pm', 'ae'];

    /** A cost no pool reaches: "spend everything left" as a plain spend. */
    private const WHOLE_POOL = 1000000;

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        parent::__construct();
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * Takes up to $cost from the actor's pool and answers what was
     * actually taken — the whole cost, the remainder when the pool held
     * less, or zero when it was already empty.
     */
    public function spend(ActorInterface $actor, string $trait, int $cost): int
    {
        if ($cost <= 0 || !in_array($trait, self::POOL_TRAITS, true)) {
            return 0;
        }

        if (!isset($actor->caracs) || !count((array) $actor->caracs)) {
            $actor->get_caracs();
        }
        $max = (int) ($actor->caracs->$trait ?? 0);

        if ($actor->isSimulated() || SimulationGuard::blocksWrite()) {
            $spent = min($cost, max(0, $actor->getRemaining($trait)));
            $this->syncTurn($actor, $trait, $actor->getRemaining($trait) - $spent);

            return $spent;
        }

        $playerId = $actor->getId();
        $after = $this->conn->transactional(function (Connection $conn) use ($playerId, $trait, $cost, $max): int {
            $conn->executeStatement(
                'INSERT IGNORE INTO players_bonus (player_id, name, n) VALUES (?, ?, 0)',
                [$playerId, $trait]
            );
            /* The guard IS the write: the floor is applied by the same
             * statement that debits, so two hands spending the last
             * point serialize on the row and the second takes nothing. */
            $conn->executeStatement(
                'UPDATE players_bonus SET n = GREATEST(n - ?, ?) WHERE player_id = ? AND name = ?',
                [$cost, -$max, $playerId, $trait]
            );

            return (int) $conn->fetchOne(
                'SELECT n FROM players_bonus WHERE player_id = ? AND name = ?',
                [$playerId, $trait]
            );
        });

        /* The split second's OTHER spender may colour this answer, never
         * the stored pool: it reports at most what stood in memory. */
        $spent = min($cost, max(0, $actor->getRemaining($trait) - max(0, $max + $after)));
        $this->syncTurn($actor, $trait, max(0, $max + $after));

        return $spent;
    }

    /** Empties the pool and answers what it held. */
    public function drain(ActorInterface $actor, string $trait): int
    {
        return $this->spend($actor, $trait, self::WHOLE_POOL);
    }

    /** Keeps the in-memory turn state in step, as putBonus does. */
    private function syncTurn(ActorInterface $actor, string $trait, int $remaining): void
    {
        if (!$actor instanceof \Classes\Player) {
            return;
        }
        if (!isset($actor->turn)) {
            $actor->turn = (object) [];
        }
        $actor->turn->$trait = $remaining;
    }
}
