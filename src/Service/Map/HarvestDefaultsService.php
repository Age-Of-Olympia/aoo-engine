<?php

namespace App\Service\Map;

use App\Service\AdminSettingsService;

/**
 * How much life a harvestable resource is created with.
 *
 * It was a constant in a migration, which meant changing it took a developer
 * and a deployment for what is a balance dial: how many blows it takes to fell
 * a tree. It lives in the admin settings instead, and pre-fills the PV of a
 * new resource type.
 *
 * Only the DEFAULT. A type that wants its own figure keeps it — the value is
 * copied at creation, never read back afterwards, so raising the default never
 * silently re-balances the world.
 */
final class HarvestDefaultsService
{
    public const SETTING = 'harvest_default_pv';
    public const DEFAULT_PV = 100;

    /** Kept sane: zero life would make a resource fall to any breath. */
    private const MIN_PV = 1;
    private const MAX_PV = 10000;

    private AdminSettingsService $settings;

    public function __construct(?AdminSettingsService $settings = null)
    {
        $this->settings = $settings ?? new AdminSettingsService();
    }

    public function pv(): int
    {
        $stored = (int) $this->settings->get(self::SETTING, (string) self::DEFAULT_PV);

        return $stored < self::MIN_PV ? self::DEFAULT_PV : min($stored, self::MAX_PV);
    }

    public function setPv(int $pv): void
    {
        $this->settings->set(self::SETTING, (string) max(self::MIN_PV, min($pv, self::MAX_PV)));
    }
}
