<?php

namespace App\Service\Action;

/**
 * Emits a one-off warning when a type-level resolver falls through to its
 * empty default because no seeded config row covers an action's type ancestry.
 *
 * The action engine is data-driven and fails soft: a missing ActionTypeXp /
 * ActionTypeLog row yields no XP / no log line rather than an error. That is
 * silent by design, which hides the two failure modes worth knowing about — a
 * deploy that skipped the seed migrations, and a new Action subclass whose type
 * key was never seeded. This turns that silence into a single log line per
 * (context, type key) per process, so a real misconfiguration is visible
 * without flooding the log on every action.
 */
final class TypeConfigWarning
{
    /** @var array<string, true> */
    private static array $seen = [];

    /** @var (callable(string): void)|null */
    private static $sink = null;

    /**
     * @param list<string> $ancestry closest-first type keys that were searched
     */
    public static function once(string $context, array $ancestry): void
    {
        $typeKey = $ancestry[0] ?? '(none)';
        $dedupeKey = $context . ':' . $typeKey;
        if (isset(self::$seen[$dedupeKey])) {
            return;
        }
        self::$seen[$dedupeKey] = true;

        $message = sprintf(
            '[action-config] no %s config for action type "%s" (searched: %s) — were the seed migrations run?',
            $context,
            $typeKey,
            implode(', ', $ancestry),
        );

        (self::$sink ?? 'error_log')($message);
    }

    /**
     * Test seam: route messages to a collector instead of the error log.
     * Returns the previous sink so a caller can restore it (like ini_set).
     *
     * @param (callable(string): void)|null $sink null restores the default error_log sink
     * @return (callable(string): void)|null
     */
    public static function setSink(?callable $sink): ?callable
    {
        $previous = self::$sink;
        self::$sink = $sink;

        return $previous;
    }

    /**
     * Test seam: forget what has already been warned about.
     */
    public static function reset(): void
    {
        self::$seen = [];
    }
}
