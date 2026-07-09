<?php

namespace App\Service;

use Classes\Db;

/**
 * Tiny key/value store for admin-configurable settings (admin_settings table).
 *
 * Reusable for any scalar admin setting; today it backs the PNJ retirement plan
 * (PnjAdminService). Degrades gracefully to the caller's default if the table
 * has not been created yet, and lazily creates it on write — same resilience
 * pattern as AdminMenuAccessService.
 */
class AdminSettingsService
{
    /**
     * Read a setting, or $default when unset (or the table is absent).
     */
    public function get(string $name, string $default = ''): string
    {
        try {
            $res = (new Db())->exe('SELECT value FROM admin_settings WHERE name = ?', [$name]);
            if ($res->num_rows) {
                return (string) $res->fetch_assoc()['value'];
            }
        } catch (\Throwable $e) {
            // Table not created yet → default.
        }

        return $default;
    }

    /**
     * Upsert a setting value.
     */
    public function set(string $name, string $value): void
    {
        $this->ensureTable();

        (new Db())->exe(
            'INSERT INTO admin_settings (name, value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$name, $value]
        );
    }

    /**
     * Create the store if it does not exist. Keeps settings working before the
     * migration is applied; idempotent.
     */
    private function ensureTable(): void
    {
        (new Db())->exe(
            "CREATE TABLE IF NOT EXISTS admin_settings (
                name VARCHAR(64) NOT NULL PRIMARY KEY,
                value VARCHAR(255) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
