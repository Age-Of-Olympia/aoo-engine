<?php

namespace App\Service;

/**
 * How likely an item is to drop at death, set from the admin home (Butin).
 * An item's own chance (items.lootChance) replaces the default; the rest
 * applies on top of it. Every value is a percentage.
 */
final class LootRulesService
{
    public const DEFAULT_CHANCE = 'loot_default_chance';
    public const PLAYER_EQUIPPED_SHARE = 'loot_player_equipped_share';
    public const NPC_CARRIED_CHANCE = 'loot_npc_carried_chance';
    public const NPC_EQUIPPED_CHANCE = 'loot_npc_equipped_chance';

    /** The values the game shipped with. */
    public const DEFAULTS = [
        self::DEFAULT_CHANCE => 30,
        self::PLAYER_EQUIPPED_SHARE => 50,
        self::NPC_CARRIED_CHANCE => 100,
        self::NPC_EQUIPPED_CHANCE => 0,
    ];

    private AdminSettingsService $settings;

    public function __construct(?AdminSettingsService $settings = null)
    {
        $this->settings = $settings ?? new AdminSettingsService();
    }

    public function get(string $name): int
    {
        return (int) $this->settings->get($name, (string) self::DEFAULTS[$name]);
    }

    public function set(string $name, int $percent): void
    {
        $this->settings->set($name, (string) max(0, min(100, $percent)));
    }

    /**
     * @param int|null $itemChance the item's own chance, null = the default
     */
    public function chance(?int $itemChance, bool $equipped, bool $npc): int
    {
        if ($npc) {
            return $this->get($equipped ? self::NPC_EQUIPPED_CHANCE : self::NPC_CARRIED_CHANCE);
        }

        $chance = $itemChance ?? $this->get(self::DEFAULT_CHANCE);

        return $equipped ? intdiv($chance * $this->get(self::PLAYER_EQUIPPED_SHARE), 100) : $chance;
    }
}
