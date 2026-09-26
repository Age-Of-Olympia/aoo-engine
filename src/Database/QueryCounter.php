<?php

namespace App\Database;

use Classes\Db;

/**
 * SQL queries run by the current request, legacy Db and Doctrine alike,
 * with the time spent in them. Shown in the page footer and, for XHR and
 * fetch responses, sent as a Server-Timing entry (js/main.js adds them up).
 *
 * It also counts the writes, per table: a per-request cache keeps what it
 * read while the writes to its tables have not moved, whoever wrote and
 * through which connection.
 */
final class QueryCounter
{
    private static int $count = 0;
    private static float $ms = 0.0;
    private static int $writes = 0;
    /** @var array<string, int> writes per table; '*' = a write whose table was not recognised */
    private static array $tableWrites = [];

    /**
     * @template T
     * @param callable(): T $query
     * @return T
     */
    public static function time(callable $query, string $sql = ''): mixed
    {
        $start = hrtime(true);
        try {
            return $query();
        } finally {
            self::$count++;
            self::$ms += (hrtime(true) - $start) / 1e6;
            if (Db::isWriteStatement($sql)) {
                self::$writes++;
                $table = preg_match('/^\s*(?:(?:INSERT|REPLACE)(?:\s+IGNORE)?\s+INTO|UPDATE|DELETE\s+FROM|TRUNCATE(?:\s+TABLE)?)\s+`?(\w+)/i', $sql, $m)
                    ? strtolower($m[1])
                    : '*';
                self::$tableWrites[$table] = (self::$tableWrites[$table] ?? 0) + 1;
            }
        }
    }

    /** Writes run so far: a cache filled at another value is stale. */
    public static function writes(): int
    {
        return self::$writes;
    }

    /** Writes run so far on these tables, unrecognised writes included. */
    public static function writesTo(string ...$tables): int
    {
        $n = self::$tableWrites['*'] ?? 0;
        foreach ($tables as $table) {
            $n += self::$tableWrites[strtolower($table)] ?? 0;
        }

        return $n;
    }

    public static function count(): int
    {
        return self::$count;
    }

    public static function milliseconds(): float
    {
        return self::$ms;
    }

    /** `db;dur=…;desc="…"`: count in the description, SQL time as the duration. */
    public static function serverTiming(): string
    {
        return 'db;dur=' . round(self::$ms, 1) . ';desc="' . self::$count . '"';
    }

    /**
     * Buffers a non-page response so its Server-Timing header can still be
     * sent once the script ends. Pages keep streaming: their count goes in
     * the footer instead.
     */
    public static function reportOnXhr(): void
    {
        if (PHP_SAPI === 'cli' || ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'document') {
            return;
        }
        ob_start();
        register_shutdown_function(static function (): void {
            if (!headers_sent()) {
                header('Server-Timing: ' . self::serverTiming());
            }
        });
    }
}
