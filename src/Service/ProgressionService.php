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
 * Legacy reads go through {@see \Classes\Player::get_row()}, which joins the
 * satellite into `$player->data->xp` and friends.
 */
final class ProgressionService
{
    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
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
        });
    }

    /**
     * Experience and level of several entities in one read; an entity without
     * a row reads the defaults.
     *
     * @param list<int> $playerIds
     * @return array<int, array{xp: int, rank: int}>
     */
    public function of(array $playerIds): array
    {
        $levels = array_fill_keys($playerIds, ['xp' => 0, 'rank' => 1]);
        if ($playerIds === []) {
            return $levels;
        }

        foreach ($this->conn->fetchAllAssociative(
            'SELECT player_id, xp, `rank` FROM progression WHERE player_id IN (?)',
            [$playerIds],
            [\Doctrine\DBAL\ArrayParameterType::INTEGER]
        ) as $row) {
            $levels[(int) $row['player_id']] = ['xp' => (int) $row['xp'], 'rank' => (int) $row['rank']];
        }

        return $levels;
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

            return $paid === 1;
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

        return $this->conn->executeStatement(
            'UPDATE progression SET xp = ?, bonus_points = bonus_points + (xp - ?) WHERE xp > ?',
            [$ceiling, $ceiling, $ceiling]
        );
    }

    /**
     * Give the entity its satellite row, so an increment or a conditional
     * write always finds one.
     *
     * Who gets one: {@see PlaysTurns::SQL_PREDICATE}. Anything else has no row
     * here and reads the defaults.
     */
    public function ensureRow(int $playerId): void
    {
        $this->conn->executeStatement(
            "INSERT IGNORE INTO progression (player_id)
             SELECT p.id
               FROM players p
               LEFT JOIN races r ON r.name = p.race
              WHERE p.id = ? AND " . PlaysTurns::SQL_PREDICATE,
            [$playerId]
        );
    }
}
