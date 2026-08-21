<?php

namespace App\Service;

/**
 * The game's current season — a global setting (admin_settings), no longer
 * a naming convention.
 *
 * Plans carry their season as a column (`plans.season`, NULL = every
 * season); everything that lists plans by season defaults to the current
 * one read here.
 */
class SeasonService
{
    public const SETTING_CURRENT = 'current_season';

    /** The running season when the setting does not exist yet. */
    public const DEFAULT_SEASON = 2;

    /** @var int|null Per-request cache. */
    private static ?int $current = null;

    private AdminSettingsService $settings;

    public function __construct(?AdminSettingsService $settings = null)
    {
        $this->settings = $settings ?? new AdminSettingsService();
    }

    public function current(): int
    {
        if (self::$current === null) {
            $raw = $this->settings->get(self::SETTING_CURRENT, (string) self::DEFAULT_SEASON);
            self::$current = is_numeric($raw) && (int) $raw >= 1 ? (int) $raw : self::DEFAULT_SEASON;
        }

        return self::$current;
    }

    public function setCurrent(int $season): void
    {
        if ($season < 1) {
            throw new \InvalidArgumentException('Invalid season: ' . $season);
        }

        $this->settings->set(self::SETTING_CURRENT, (string) $season);
        self::forget();
    }

    /** Invalidate the per-request cache (writes, tests). */
    public static function forget(): void
    {
        self::$current = null;
    }
}
