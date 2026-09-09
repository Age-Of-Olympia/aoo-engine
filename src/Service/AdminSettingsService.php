<?php

namespace App\Service;

use Classes\Db;

/** Key/value store for admin-configurable settings (admin_settings table). */
class AdminSettingsService
{
    /** Read a setting, or $default when unset. */
    public function get(string $name, string $default = ''): string
    {
        $res = (new Db())->exe('SELECT value FROM admin_settings WHERE name = ?', [$name]);

        return $res->num_rows ? (string) $res->fetch_assoc()['value'] : $default;
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
