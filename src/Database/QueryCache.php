<?php

namespace App\Database;

/**
 * Reads kept for the rest of a web request, each one until a write touches
 * one of its tables (QueryCounter::writesTo). A move reloads the same
 * character in every request it makes, several times over: this keeps one
 * read per request.
 *
 * Web only: a cron or a console command runs long while other processes
 * write, which QueryCounter cannot see, so there the loader always runs.
 */
final class QueryCache
{
    /** @var array<string, array{int, mixed}> key => [writes to its tables when read, value] */
    private static array $entries = [];

    /**
     * @template T
     * @param list<string> $tables what the value is read from
     * @param callable(): T $load
     * @return T
     */
    public static function remember(array $tables, string $key, callable $load): mixed
    {
        if (PHP_SAPI === 'cli') {
            return $load();
        }

        $writes = QueryCounter::writesTo(...$tables);
        if (isset(self::$entries[$key]) && self::$entries[$key][0] === $writes) {
            return self::$entries[$key][1];
        }

        $value = $load();
        self::$entries[$key] = [QueryCounter::writesTo(...$tables), $value];

        return $value;
    }
}
