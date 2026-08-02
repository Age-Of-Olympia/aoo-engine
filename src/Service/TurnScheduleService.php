<?php

namespace App\Service;

/**
 * Turn scheduling rules: how long a player's turn lasts (driven by the
 * speed carac) and the window inside which a player may manually move
 * their next turn.
 *
 * Replaces the old "DLA glissante" (`dlag`) option: instead of re-anchoring
 * the schedule on the moment the player happens to load the new-turn page,
 * the player picks the exact time of their next turn between the currently
 * scheduled one and the potential following one.
 */
class TurnScheduleService
{
    /** Base turn duration for a speed of SPD_BASELINE. */
    public const BASE_TURN_SECONDS = 86400;

    /** Speed value at which a turn lasts exactly BASE_TURN_SECONDS. */
    public const SPD_BASELINE = 10;

    /** Seconds gained on the turn duration per speed point above baseline. */
    public const SECONDS_PER_SPD_POINT = 3600;

    /**
     * Vitesse de référence pour les durées qui ne dépendent d'aucun
     * joueur en particulier — les éléments posés sur la carte.
     *
     * Un élément de carte n'appartient à personne : aucun tour ne le
     * décrémente, c'est le cron horaire qui l'efface. Sa durée reste
     * donc une durée RÉELLE — mais on l'écrit en tours, comme celle des
     * effets, et on la convertit ici. À vitesse 16, un tour dure 18 h.
     */
    public const REFERENCE_SPD = 16;

    /** Durée réelle d'un tour de référence, en secondes (18 h). */
    public static function referenceTurnSeconds(): int
    {
        return self::turnDurationSeconds(self::REFERENCE_SPD);
    }

    /**
     * Duration of one full turn for the given speed carac.
     * Each point above baseline shortens the turn by one hour.
     */
    public static function turnDurationSeconds(int $spd): int
    {
        return self::BASE_TURN_SECONDS - (($spd - self::SPD_BASELINE) * self::SECONDS_PER_SPD_POINT);
    }

    /**
     * Window inside which the player may reschedule their next turn:
     * from the currently scheduled turn (no free turn by moving it earlier)
     * up to the potential following turn (current + one turn duration).
     *
     * Bounds are aligned on whole minutes (min rounded up, max rounded down)
     * because the UI uses a minute-granularity datetime-local input; the
     * server validates against the exact same bounds it rendered.
     *
     * @return array{min: int, max: int} Unix timestamps
     */
    public static function rescheduleWindow(int $nextTurnTime, int $spd): array
    {
        $min = (int) ceil($nextTurnTime / 60) * 60;
        $max = (int) floor(($nextTurnTime + self::turnDurationSeconds($spd)) / 60) * 60;

        return array('min' => $min, 'max' => $max);
    }

    /**
     * Whether $candidate is an acceptable new next-turn time for a player
     * whose turn is currently scheduled at $nextTurnTime.
     */
    public static function isWithinRescheduleWindow(int $candidate, int $nextTurnTime, int $spd): bool
    {
        $window = self::rescheduleWindow($nextTurnTime, $spd);

        return $candidate >= $window['min'] && $candidate <= $window['max'];
    }

    /**
     * Persist a new next-turn time. Callers must have validated the value
     * with isWithinRescheduleWindow() first.
     *
     * The right to reschedule is spent along with it: one reschedule per turn
     * cycle, the flag cleared when the turn refreshes (NewTurnView). Where the
     * turn is stored is TurnService's business — the rules stay here.
     */
    public function reschedule(int $playerId, int $newNextTurnTime): void
    {
        (new TurnService())->reschedule($playerId, $newNextTurnTime);
    }
}
