<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Simulation\SimulationGuard;
use Doctrine\DBAL\Connection;

/**
 * The one gateway to what an entity earns: experience, the rank it reaches,
 * the season's banked overflow, and the PI that experience mints and
 * characteristic upgrades spend. They live in `progression`, keyed by
 * player_id.
 *
 * Every write also updates the matching `players` column, because code still
 * reads those during the transition. When the columns go, the dual write goes
 * with them and only the satellite statement of each method remains — that is
 * the whole point of routing writes through here first.
 *
 * Reads are not routed here: {@see \Classes\Player::get_row()} joins the
 * satellite, so everything reading `$player->data->xp` and friends keeps
 * working unchanged.
 */
final class ProgressionService extends BaseService
{
    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        parent::__construct();
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * Earn experience and the PI it mints, and settle on the rank that much
     * experience reaches. A null rank leaves the rank where it stands.
     */
    public function gain(int $playerId, int $xp, int $pi, ?int $rank = null): void
    {
        if (SimulationGuard::blocksWrite()) {
            return;
        }

        $this->conn->transactional(function (Connection $conn) use ($playerId, $xp, $pi, $rank): void {
            $this->ensureRow($playerId);

            $setRank = $rank === null ? '' : ', `rank` = ?';
            $rankParam = $rank === null ? [] : [$rank];

            $conn->executeStatement(
                'UPDATE progression SET xp = xp + ?, pi = pi + ?' . $setRank . ' WHERE player_id = ?',
                array_merge([$xp, $pi], $rankParam, [$playerId])
            );

            /* The mirror: this statement goes when the columns do. */
            $conn->executeStatement(
                'UPDATE players SET xp = xp + ?, pi = pi + ?' . $setRank . ' WHERE id = ?',
                array_merge([$xp, $pi], $rankParam, [$playerId])
            );
        });
    }

    /** Credit PI alone — an upgrade refunded, an audited balance corrected. */
    public function addPi(int $playerId, int $amount): void
    {
        $this->gain($playerId, 0, $amount);
    }

    /**
     * Debit PI, and answer whether it went through.
     *
     * The balance is checked by the UPDATE itself and the affected-row count
     * is the answer, so two requests spending at the same instant cannot both
     * take the last point. The cost is strictly positive, which is what makes
     * a matched row always a changed row.
     */
    public function spendPi(int $playerId, int $cost): bool
    {
        /* A preview pays nothing, and reports the spend as accepted so the
         * caller walks the same branch it would in play. */
        if (SimulationGuard::blocksWrite()) {
            return true;
        }

        return $this->conn->transactional(function (Connection $conn) use ($playerId, $cost): bool {
            $this->ensureRow($playerId);

            $paid = $conn->executeStatement(
                'UPDATE progression SET pi = pi - ? WHERE player_id = ? AND pi >= ?',
                [$cost, $playerId, $cost]
            );

            if ($paid !== 1) {
                return false;
            }

            /* The mirror: this statement goes when the columns do. */
            $conn->executeStatement(
                'UPDATE players SET pi = pi - ? WHERE id = ?',
                [$cost, $playerId]
            );

            return true;
        });
    }

    /**
     * Apply the season's experience ceiling: experience settles on it, and the
     * bonus points take what the statement still reads as the excess by then —
     * assignments are evaluated left to right, so the ceiling is already in
     * place. Answers how many characters were over it.
     */
    public function bankOverflowXp(int $ceiling): int
    {
        if (SimulationGuard::blocksWrite()) {
            return 0;
        }

        return $this->conn->transactional(function (Connection $conn) use ($ceiling): int {
            $banked = $conn->executeStatement(
                'UPDATE progression SET xp = ?, bonus_points = bonus_points + (xp - ?) WHERE xp > ?',
                [$ceiling, $ceiling, $ceiling]
            );

            /* The mirror: this statement goes when the columns do. */
            $conn->executeStatement(
                'UPDATE players SET xp = ?, bonus_points = bonus_points + (xp - ?) WHERE xp > ?',
                [$ceiling, $ceiling, $ceiling]
            );

            return $banked;
        });
    }

    /**
     * Give the entity its satellite row, seeded from the columns it mirrors,
     * so an increment or a conditional write never starts from a blank slate.
     *
     * Who gets one: {@see PlaysTurns::SQL_PREDICATE}. Anything else has no row
     * here and keeps answering from `players` through the join.
     */
    public function ensureRow(int $playerId): void
    {
        $this->conn->executeStatement(
            "INSERT IGNORE INTO progression (player_id, xp, `rank`, bonus_points, pi)
             SELECT p.id, p.xp, p.`rank`, p.bonus_points, p.pi
               FROM players p
               LEFT JOIN races r ON r.name = p.race
              WHERE p.id = ? AND " . PlaysTurns::SQL_PREDICATE,
            [$playerId]
        );
    }
}
