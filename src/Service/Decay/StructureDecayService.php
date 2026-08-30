<?php

namespace App\Service\Decay;

use App\Factory\EntityManagerFactory;
use App\Service\BuildingService;
use App\Service\TurnScheduleService;
use Doctrine\DBAL\Connection;

/**
 * Decay of player-built constructions — docs/design-decay-structures.md.
 *
 * # Membership is the rule
 *
 * A row in `entity_decay` IS the criterion. It is written when a player
 * builds ({@see enrol()}), and what Tiled or the admin placed has none, so
 * the pass never even reads it. `players.owner_id` could not serve: it is
 * set on the admin's placement path too, and means "somebody owns this",
 * not "a player built this".
 *
 * # One clock, carrying the grace
 *
 * `decay_from` means "decay is owed from this instant". Using a building
 * pushes it to now + grace ({@see touch()}); the pass advances it by the
 * WHOLE turns it applied, so the turn grid never drifts. Keeping a separate
 * last-used stamp instead would let the catch-up bill the turns during
 * which the building was in use.
 *
 * # Every decay is really applied
 *
 * An abandoned wall must READ as abandoned to anyone who looks — the map,
 * the sheet, a fight. So nothing is computed at read time; the deficit in
 * `players_bonus` is always current. What the design avoids is not the
 * ticks but the LOOP: {@see applyDue()} is one set-based statement whose
 * catch-up gives the exact result of having ticked every turn, and the only
 * PHP loop is over what actually collapsed.
 */
final class StructureDecayService
{
    /** Below this share of its life, a construction is flagged to its faction. */
    public const ALERT_BELOW_PCT = 75;

    /** @var list<int> BASE, BASELINE, PER_POINT — bound wherever a turn is computed. */
    private const TURN_PARAMS = [
        TurnScheduleService::BASE_TURN_SECONDS,
        TurnScheduleService::SPD_BASELINE,
        TurnScheduleService::SECONDS_PER_SPD_POINT,
    ];

    private Connection $conn;

    private DecayDefaultsService $defaults;

    public function __construct(?DecayDefaultsService $defaults = null)
    {
        $this->conn = EntityManagerFactory::getEntityManager()->getConnection();
        $this->defaults = $defaults ?? new DecayDefaultsService();
    }

    /**
     * This construction is a player's: it decays from now + its grace.
     *
     * Called at PLACEMENT, the one moment provenance is known — a finished
     * site cannot tell how it was raised. A site therefore carries its row
     * while still being built, and {@see applyDue()} leaves it alone until
     * the last stone.
     */
    public function enrol(int $entityId, ?int $now = null): void
    {
        $now ??= time();

        $this->conn->executeStatement(
            'INSERT INTO entity_decay (player_id, decay_from) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE decay_from = VALUES(decay_from)',
            [$entityId, $now + $this->graceSecondsOf($entityId)]
        );
    }

    /**
     * Someone used it: the horizon moves, nothing is healed.
     *
     * A construction used once per grace period is never due, so the pass
     * never reads it. Silently does nothing for anything not enrolled —
     * walking through a Tiled arch must not enrol it.
     */
    public function touch(int $entityId, ?int $now = null): void
    {
        $now ??= time();

        $this->conn->executeStatement(
            'UPDATE entity_decay SET decay_from = ? WHERE player_id = ?',
            [$now + $this->graceSecondsOf($entityId), $entityId]
        );
    }

    /**
     * A road is walked back into shape: the horizon moves AND the wear is
     * mended.
     *
     * Roads alone heal, because decay is their only source of damage — so
     * mending in full needs no accounting of what took what. The day
     * something can deliberately damage a road, this has to be revisited: a
     * step must not undo sabotage.
     */
    public function touchAndHeal(int $entityId, ?int $now = null): void
    {
        $this->touch($entityId, $now);

        $this->conn->executeStatement(
            "DELETE FROM players_bonus WHERE player_id = ? AND name = 'pv'",
            [$entityId]
        );
    }

    /**
     * Apply every turn owed to every enrolled construction, then destroy
     * what this pass — and only this pass — brought to zero.
     *
     * @return array{decayed: int, collapsed: list<int>}
     */
    public function run(?int $now = null, int $collapseCap = 200): array
    {
        $now ??= time();

        /* Who this pass will finish off, asked BEFORE applying, and only
           among those still STANDING: a building already at zero from a
           siege is none of decay's business — the game leaves it « en
           ruine », and felling it here would quietly change how sieges
           end. */
        $doomed = $this->conn->fetchFirstColumn(
            'SELECT e.id ' . $this->dueFrom() . '
                AND r.pv + COALESCE(b.n, 0) > 0
                AND r.pv + COALESCE(b.n, 0) - ' . $this->rateExpr() . ' * ' . $this->owedExpr() . ' <= 0
              ORDER BY e.id
              LIMIT ' . max(1, $collapseCap),
            /* Bindings follow the STRING, and here the WHERE of dueFrom()
               comes before the projection — the reverse of the INSERT, whose
               SELECT list is written first. Reusing dueParams() here fed the
               rate to a turn length and nothing ever matched. */
            array_merge(
                [$now], self::TURN_PARAMS,
                [$this->defaults->rate()],
                [$now], self::TURN_PARAMS
            )
        );

        $decayed = $this->applyDue($now);

        $building = new BuildingService();
        $collapsed = [];

        foreach ($doomed as $id) {
            /* remove() cascades the entity_decay row away with the entity. */
            if ($building->remove((int) $id)) {
                $collapsed[] = (int) $id;
            }
        }

        return ['decayed' => $decayed, 'collapsed' => $collapsed];
    }

    /**
     * The pass. Two set-based writes, whatever the size of the world: the
     * deficit, then the clock.
     *
     * An INSERT rather than an UPDATE because an untouched construction has
     * no deficit row at all — joining one would have let anything still at
     * full life escape decay for ever.
     *
     * `FLOOR(elapsed / turn)` is the catch-up: normally 1, more after a run
     * that did not happen. It also makes the pass idempotent — running it
     * twice in one turn changes nothing the second time.
     *
     * @return int constructions the pass took life from
     */
    public function applyDue(int $now): int
    {
        return (int) $this->conn->transactional(function (Connection $conn) use ($now): int {
            $applied = (int) $conn->executeStatement(
                "INSERT INTO players_bonus (player_id, name, n)
                 SELECT e.id, 'pv',
                        GREATEST(-r.pv, COALESCE(b.n, 0) - " . $this->rateExpr() . ' * ' . $this->owedExpr() . ')
                 ' . $this->dueFrom() . '
                 ON DUPLICATE KEY UPDATE n = VALUES(n)',
                $this->dueParams($now, [$this->defaults->rate()])
            );

            /* The clock moves by WHOLE turns, never to now: the turn grid
               must not drift. Second statement because INSERT … SELECT
               cannot also write the satellite. */
            $conn->executeStatement(
                'UPDATE entity_decay d
                   JOIN players e ON e.id = d.player_id
                   JOIN races r   ON CONVERT(r.name USING utf8mb4) = CONVERT(e.race USING utf8mb4)
                    SET d.decay_from = d.decay_from + ' . $this->turnExpr() . ' * ' . $this->owedExpr() . '
                  WHERE NOT EXISTS (SELECT 1 FROM construction_sites cs WHERE cs.player_id = e.id)
                    AND d.decay_from <= ? - ' . $this->turnExpr(),
                array_merge(
                    self::TURN_PARAMS,
                    [$now], self::TURN_PARAMS,
                    [$now], self::TURN_PARAMS
                )
            );

            return $applied;
        });
    }

    /** Turn length, per row, from the type's own speed. */
    private function turnExpr(): string
    {
        return '(? - (r.spd - ?) * ?)';
    }

    /** Whole turns owed by this construction. */
    private function owedExpr(): string
    {
        return 'FLOOR((? - d.decay_from) / ' . $this->turnExpr() . ')';
    }

    /** Its own figure when it has one, the global dial otherwise. */
    private function rateExpr(): string
    {
        return 'COALESCE(r.decay_rate, ?)';
    }

    /**
     * The constructions that owe a turn: enrolled (so player-built), not
     * still a building site, and past their horizon.
     */
    private function dueFrom(): string
    {
        return "FROM players e
                JOIN races r        ON CONVERT(r.name USING utf8mb4) = CONVERT(e.race USING utf8mb4)
                JOIN entity_decay d ON d.player_id = e.id
                LEFT JOIN players_bonus b ON b.player_id = e.id AND b.name = 'pv'
               WHERE NOT EXISTS (SELECT 1 FROM construction_sites cs WHERE cs.player_id = e.id)
                 AND d.decay_from <= ? - " . $this->turnExpr();
    }

    /**
     * Positional bindings for rate + owed + the due filter, in the order the
     * expressions above appear.
     *
     * @param list<int> $rate
     * @return list<int>
     */
    private function dueParams(int $now, array $rate): array
    {
        return array_merge(
            $rate,
            [$now], self::TURN_PARAMS,
            [$now], self::TURN_PARAMS
        );
    }

    /**
     * The grace of a type, in seconds: its own figure when it has one, the
     * global dial otherwise, times that type's own turn length.
     */
    private function graceSecondsOf(int $entityId): int
    {
        $row = $this->conn->fetchAssociative(
            'SELECT r.decay_grace, r.spd
               FROM players e
               JOIN races r ON CONVERT(r.name USING utf8mb4) = CONVERT(e.race USING utf8mb4)
              WHERE e.id = ?',
            [$entityId]
        );

        $turns = $row !== false && $row['decay_grace'] !== null
            ? (int) $row['decay_grace']
            : $this->defaults->graceTurns();

        $spd = $row !== false ? (int) $row['spd'] : TurnScheduleService::SPD_BASELINE;

        return $turns * TurnScheduleService::turnDurationSeconds($spd);
    }
}
