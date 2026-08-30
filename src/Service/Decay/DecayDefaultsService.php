<?php

namespace App\Service\Decay;

use App\Service\AdminSettingsService;

/**
 * The two global dials of structure decay, on the `harvest_default_pv`
 * model: a figure in `admin_settings`, edited from the admin dashboard
 * rather than by a developer and a deployment.
 *
 * Unlike HarvestDefaultsService, these are READ at use, not copied into
 * each type at creation. A harvestable's PV is a starting stock, so raising
 * that default must not re-heal standing trees; decay is an ongoing rule
 * the team will tune while watching players, and turning the dial has to
 * move the world.
 *
 * In practice a new grace takes effect from each construction's next use,
 * since `decay_from` is set to now + grace — progressive, no migration.
 */
final class DecayDefaultsService
{
    public const SETTING_RATE = 'decay_rate_default';
    public const SETTING_GRACE = 'decay_grace_turns';

    /** Placeholders: the real figures are an admin decision, taken later. */
    public const DEFAULT_RATE = 1;
    public const DEFAULT_GRACE_TURNS = 3;

    /** A rate of 0 would mean "never decays", which the absence of a row says. */
    private const MIN_RATE = 1;
    private const MAX_RATE = 1000;

    /** Zero grace is legitimate: decay then starts the moment use stops. */
    private const MIN_GRACE = 0;
    private const MAX_GRACE = 1000;

    private AdminSettingsService $settings;

    public function __construct(?AdminSettingsService $settings = null)
    {
        $this->settings = $settings ?? new AdminSettingsService();
    }

    public function rate(): int
    {
        $stored = (int) $this->settings->get(self::SETTING_RATE, (string) self::DEFAULT_RATE);

        return $stored < self::MIN_RATE ? self::DEFAULT_RATE : min($stored, self::MAX_RATE);
    }

    public function setRate(int $rate): void
    {
        $this->settings->set(self::SETTING_RATE, (string) max(self::MIN_RATE, min($rate, self::MAX_RATE)));
    }

    /** In TURNS, never in days — a structure turn lasts 18 h at spd 16. */
    public function graceTurns(): int
    {
        $stored = $this->settings->get(self::SETTING_GRACE, (string) self::DEFAULT_GRACE_TURNS);

        if (!is_numeric($stored)) {
            return self::DEFAULT_GRACE_TURNS;
        }

        return max(self::MIN_GRACE, min((int) $stored, self::MAX_GRACE));
    }

    public function setGraceTurns(int $turns): void
    {
        $this->settings->set(self::SETTING_GRACE, (string) max(self::MIN_GRACE, min($turns, self::MAX_GRACE)));
    }
}
