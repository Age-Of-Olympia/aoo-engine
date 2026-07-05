<?php

namespace App\Simulation;

/**
 * Request-scoped switch that makes the legacy Classes\Db refuse writes while a
 * simulation runs.
 *
 * The action simulator runs the real engine, which can reach world-mutating
 * paths a DB-free SimulatedPlayer cannot override (notably a real
 * Classes\Item::add_item). The per-method no-ops on SimulatedPlayer avoid DB
 * reads and heavy work; this guard is the persistence boundary checked by the
 * two write chokepoints — Classes\Db::exe() (all SQL) and Classes\Json's file
 * writes — so a preview persists nothing through them. Reads are unaffected.
 * (A write via a path that bypasses both — e.g. a future Doctrine flush() — would
 * not be caught; the engine currently writes only through those two.)
 */
final class SimulationGuard
{
    private static bool $active = false;
    private static int $blockedWrites = 0;

    /**
     * Run $callback with DB writes blocked, restoring the previous state
     * afterwards so nested calls behave. Returns the callback's value.
     *
     * @param callable():mixed $callback
     */
    public static function run(callable $callback): mixed
    {
        $previous = self::$active;
        self::$active = true;
        try {
            return $callback();
        } finally {
            self::$active = $previous;
        }
    }

    public static function isActive(): bool
    {
        return self::$active;
    }

    public static function recordBlockedWrite(): void
    {
        self::$blockedWrites++;
    }

    public static function blockedWrites(): int
    {
        return self::$blockedWrites;
    }

    /** Test helper: reset the blocked-write counter. */
    public static function resetBlockedWrites(): void
    {
        self::$blockedWrites = 0;
    }
}
