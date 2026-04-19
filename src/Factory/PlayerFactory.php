<?php

namespace App\Factory;

use App\Entity\EntityManagerFactory;
use App\Entity\PlayerEntity;
use App\Tutorial\TutorialHelper;
use Classes\Player;

/**
 * PlayerFactory — single canonical entry point for obtaining player instances.
 *
 * Returns either:
 *  - the legacy `Classes\Player` (drop-in for `new \Classes\Player($id)`),
 *  - a modern `PlayerEntity` (`RealPlayer` / `TutorialPlayerEntity` / `NPCEntity`)
 *    for read-only paths.
 *
 * Coexistence model: legacy and modern objects are PARALLEL representations of
 * the same row, never wrapped together. Pick one per call site. Mixing the two
 * within a single request risks cache divergence between `Classes\Player::$data`
 * and Doctrine's identity map.
 *
 * Tutorial-aware: `active*()` consult `TutorialHelper::getActivePlayerId()` so
 * callers in tutorial mode automatically get the tutorial player.
 *
 * Construction is lazy. `legacy()` does NOT load row data — callers must call
 * `get_data()` / `get_caracs()` on the returned object as today. This preserves
 * behavior of the ~50 existing `new Player()` call sites.
 */
final class PlayerFactory
{
    /**
     * Construct the legacy `Classes\Player` for a given id (lazy — no row load).
     */
    public static function legacy(int $playerId): Player
    {
        return new Player($playerId);
    }

    /**
     * Legacy `Classes\Player` for the active player (tutorial-aware).
     * Replaces `$pid = TutorialHelper::getActivePlayerId(); $player = new Player($pid);`.
     */
    public static function active(): Player
    {
        return self::legacy(self::activeId());
    }

    /**
     * Active player id (tutorial-aware). Sugar over `TutorialHelper`.
     */
    public static function activeId(): int
    {
        return TutorialHelper::getActivePlayerId();
    }

    /**
     * Legacy `Classes\Player` looked up by name (player_type='real' only),
     * or null if no such row exists.
     *
     * Thin wrapper over `Player::get_player_by_name()` that normalises the
     * legacy `Player|false` return to `?Player` — matches the shape of
     * `entity()` and lets callers use `?->` safely.
     *
     * Use when the caller has untrusted user input (missive recipients,
     * merchant exchanges, password resets) and needs to discover whether
     * the player exists before acting.
     */
    public static function legacyByName(string $name): ?Player
    {
        $player = Player::get_player_by_name($name);
        return $player === false ? null : $player;
    }

    /**
     * Modern Doctrine entity for a given id, or null if the row doesn't exist.
     * Use for READ-ONLY paths (rankings, profile pages, admin lists).
     */
    public static function entity(int $playerId): ?PlayerEntity
    {
        return EntityManagerFactory::getEntityManager()->find(PlayerEntity::class, $playerId);
    }

    /**
     * Modern entity for the active player (tutorial-aware).
     */
    public static function activeEntity(): ?PlayerEntity
    {
        return self::entity(self::activeId());
    }
}
