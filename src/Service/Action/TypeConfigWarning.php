<?php

namespace App\Service\Action;

/**
 * Emits a one-off warning when a type-level resolver falls through to its empty
 * default because no seeded config row covers an action's type ancestry.
 *
 * The action engine is data-driven and fails soft: a missing ActionTypeXp /
 * ActionTypeLog row yields no XP / no log line rather than an error. That hides
 * the two failure modes worth knowing about — a deploy that skipped the seed
 * migrations, and a new Action subclass whose type key was never seeded. This
 * logs one line per (context, type key) per process so a real misconfiguration
 * is visible without flooding the log.
 */
final class TypeConfigWarning
{
    /** @var array<string, true> */
    private static array $seen = [];

    /**
     * Suppress the log entirely. Set true by the phpunit bootstrap: the engine
     * unit tests deliberately run actions with no seeded config, and the log
     * would otherwise trip the suite's strict no-output / fail-on-risky checks.
     */
    public static bool $silenced = false;

    /**
     * @param list<string> $ancestry closest-first type keys that were searched
     */
    public static function once(string $context, array $ancestry): void
    {
        if (self::$silenced) {
            return;
        }

        $typeKey = $ancestry[0] ?? '(none)';
        $dedupeKey = $context . ':' . $typeKey;
        if (isset(self::$seen[$dedupeKey])) {
            return;
        }
        self::$seen[$dedupeKey] = true;

        error_log(sprintf(
            '[action-config] no %s config for action type "%s" (searched: %s) — were the seed migrations run?',
            $context,
            $typeKey,
            implode(', ', $ancestry),
        ));
    }
}
