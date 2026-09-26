<?php

namespace App\Service;

use App\Database\QueryCounter;
use Classes\Db;

/** Key/value store for admin-configurable settings (admin_settings table). */
class AdminSettingsService
{
    /** @var array<string, string>|null every setting, read in one query */
    private static ?array $all = null;
    private static int $readAtWrites = -1;

    /** Read a setting, or $default when unset. */
    public function get(string $name, string $default = ''): string
    {
        // Read once per request; again after any write, which may have changed one
        if (self::$all === null || self::$readAtWrites !== QueryCounter::writes()) {
            self::$all = [];
            $res = (new Db())->exe('SELECT name, value FROM admin_settings');
            while ($row = $res->fetch_assoc()) {
                self::$all[$row['name']] = (string) $row['value'];
            }
            self::$readAtWrites = QueryCounter::writes();
        }

        return self::$all[$name] ?? $default;
    }

    /**
     * Upsert a setting value.
     */
    public function set(string $name, string $value): void
    {
        (new Db())->exe(
            'INSERT INTO admin_settings (name, value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$name, $value]
        );
    }
}
