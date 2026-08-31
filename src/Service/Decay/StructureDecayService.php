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

    /**
     * A turn lasts what the TYPE says — `turnDurationSeconds` written in SQL,
     * so decay counts at the same pace as the rest of the game.
     *
     * The shape of that formula is the one thing living in two languages; the
     * numbers are bound from TurnScheduleService, so only the shape could
     * drift, and that would take the turn economy ceasing to be linear.
     */
    private const TURN = '(:base - (r.spd - :baseline) * :perPoint)';

    /** Whole turns this construction owes. */
    private const OWED = 'FLOOR((:now - d.decay_from) / ' . self::TURN . ')';

    /** Its own figure when it has one, the global dial otherwise. */
    private const RATE = 'COALESCE(r.decay_rate, :rate)';

    /**
     * The constructions that owe a turn: enrolled (so player-built), not
     * still a building site, and past their horizon.
     */
    private const DUE_FROM = "FROM players e
                JOIN races r        ON CONVERT(r.name USING utf8mb4) = CONVERT(e.race USING utf8mb4)
                JOIN entity_decay d ON d.player_id = e.id
                LEFT JOIN players_bonus b ON b.player_id = e.id AND b.name = 'pv'
               WHERE NOT EXISTS (SELECT 1 FROM construction_sites cs WHERE cs.player_id = e.id)
                 AND d.decay_from <= :now - " . self::TURN;

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
    public function run(?int $now = null): array
    {
        $now ??= time();

        /* Who this pass will finish off, asked BEFORE applying, and only
           among those still STANDING: a building already at zero from a
           siege is none of decay's business — the game leaves it « en
           ruine », and felling it here would quietly change how sieges
           end.
           No ceiling on this list. Capping it stranded whatever fell past
           the cap: those rows still decayed to zero, and the next run could
           not see them again, since it only looks at what is standing. The
           set is empty on almost every run and bounded by how many things
           can die at once. */
        $doomed = $this->conn->fetchFirstColumn(
            'SELECT e.id ' . self::DUE_FROM . '
                AND r.pv + COALESCE(b.n, 0) > 0
                AND r.pv + COALESCE(b.n, 0) - ' . self::RATE . ' * ' . self::OWED . ' <= 0
              ORDER BY e.id',
            $this->bindings($now)
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
                        GREATEST(-r.pv, COALESCE(b.n, 0) - " . self::RATE . ' * ' . self::OWED . ')
                 ' . self::DUE_FROM . '
                 ON DUPLICATE KEY UPDATE n = VALUES(n)',
                $this->bindings($now)
            );

            /* The clock moves by WHOLE turns, never to now: the turn grid
               must not drift. Second statement because INSERT … SELECT
               cannot also write the satellite. */
            $conn->executeStatement(
                'UPDATE entity_decay d
                   JOIN players e ON e.id = d.player_id
                   JOIN races r   ON CONVERT(r.name USING utf8mb4) = CONVERT(e.race USING utf8mb4)
                    SET d.decay_from = d.decay_from + ' . self::TURN . ' * ' . self::OWED . '
                  WHERE NOT EXISTS (SELECT 1 FROM construction_sites cs WHERE cs.player_id = e.id)
                    AND d.decay_from <= :now - ' . self::TURN,
                $this->bindings($now)
            );

            return $applied;
        });
    }

    /**
     * Every value the three statements can ask for, by NAME.
     *
     * They were positional, and the fragments below are reused in different
     * ORDERS: the projection writes its WHERE before its arithmetic, the
     * INSERT the other way round. One list served both, so the decay rate
     * was handed to a turn length and nothing ever collapsed. Naming them
     * removes the ordering entirely — an unused name costs nothing.
     *
     * @return array<string, int>
     */
    private function bindings(int $now): array
    {
        return [
            'now' => $now,
            'rate' => $this->defaults->rate(),
            'base' => TurnScheduleService::BASE_TURN_SECONDS,
            'baseline' => TurnScheduleService::SPD_BASELINE,
            'perPoint' => TurnScheduleService::SECONDS_PER_SPD_POINT,
        ];
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
