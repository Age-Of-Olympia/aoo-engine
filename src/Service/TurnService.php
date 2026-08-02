<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Simulation\SimulationGuard;
use Doctrine\DBAL\Connection;

/**
 * The one gateway to when an entity plays: the hour of its next turn, the
 * moment of its last action, whether it has already moved that turn, and the
 * anti-berserk delay. They live in `turns`, keyed by player_id.
 *
 * Every write also updates the matching `players` column, because code still
 * reads those during the transition. When the columns go, the dual write goes
 * with them and only the satellite statement of each method remains — that is
 * the whole point of routing writes through here first.
 *
 * Reads are not routed here: {@see \Classes\Player::get_row()} joins the
 * satellite, so everything reading `$player->data->nextTurnTime` and friends
 * keeps working unchanged.
 */
final class TurnService extends BaseService
{
    /** players columns still mirrored, keyed by their `turns` counterpart. */
    private const MIRRORED = [
        'next_turn_time' => 'nextTurnTime',
        'last_action_time' => 'lastActionTime',
        'next_turn_rescheduled' => 'nextTurnRescheduled',
        'anti_berserk_time' => 'antiBerserkTime',
    ];

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        parent::__construct();
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * Open a fresh turn: the clock moves on, the reschedule right comes back
     * and the turn's own counters restart.
     */
    public function openTurn(int $playerId, int $nextTurnTime, int $antiBerserkTime): void
    {
        $this->write($playerId, [
            'next_turn_time' => $nextTurnTime,
            'next_turn_rescheduled' => 0,
            'last_action_time' => 0,
            'anti_berserk_time' => $antiBerserkTime,
        ]);
    }

    /**
     * Move the next turn inside its window, and spend the right to do so:
     * one reschedule per turn cycle, cleared when the turn refreshes.
     */
    public function reschedule(int $playerId, int $nextTurnTime): void
    {
        $this->write($playerId, [
            'next_turn_time' => $nextTurnTime,
            'next_turn_rescheduled' => 1,
        ]);
    }

    /** Set the hour of the next turn, leaving the turn's counters alone. */
    public function setNextTurnTime(int $playerId, int $nextTurnTime): void
    {
        $this->write($playerId, ['next_turn_time' => $nextTurnTime]);
    }

    /** The same, with the counters a fresh turn would have wiped. */
    public function restartTurnClock(int $playerId, int $nextTurnTime): void
    {
        $this->write($playerId, [
            'next_turn_time' => $nextTurnTime,
            'last_action_time' => 0,
            'anti_berserk_time' => 0,
        ]);
    }

    public function touchLastAction(int $playerId, ?int $time = null): void
    {
        $this->write($playerId, ['last_action_time' => $time ?? time()]);
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
            "INSERT IGNORE INTO turns
                 (player_id, next_turn_time, last_action_time, next_turn_rescheduled, anti_berserk_time)
             SELECT p.id, p.nextTurnTime, p.lastActionTime, p.nextTurnRescheduled, p.antiBerserkTime
               FROM players p
               LEFT JOIN races r ON r.name = p.race
              WHERE p.id = ? AND " . PlaysTurns::SQL_PREDICATE,
            [$playerId]
        );
    }

    /** @param array<string, int> $values `turns` columns */
    private function write(int $playerId, array $values): void
    {
        if (SimulationGuard::blocksWrite()) {
            return;
        }

        $this->conn->transactional(function (Connection $conn) use ($playerId, $values): void {
            $this->ensureRow($playerId);

            $columns = array_keys($values);

            $conn->executeStatement(
                'UPDATE turns SET '
                    . implode(', ', array_map(static fn (string $c): string => "{$c} = ?", $columns))
                    . ' WHERE player_id = ?',
                array_merge(array_values($values), [$playerId])
            );

            /* The mirror: this statement goes when the columns do. */
            $conn->executeStatement(
                'UPDATE players SET '
                    . implode(', ', array_map(
                        static fn (string $c): string => self::MIRRORED[$c] . ' = ?',
                        $columns
                    ))
                    . ' WHERE id = ?',
                array_merge(array_values($values), [$playerId])
            );
        });
    }
}
