<?php

namespace App\Interface;

/**
 * Something that earns experience and rises through levels.
 *
 * Like {@see TakesTurnsInterface}, this is a capability rather than a place in
 * the tree: buildings will earn their OWN experience and keep their level even
 * once destroyed, since a razed building is shelved rather than deleted
 * (docs/design-playable-buildings.md §3.1).
 *
 * That last point is the reason the contract is worth naming early: whatever
 * ends up storing a level must survive `BuildingService::vanish()`, which
 * deletes `players_bonus`, `players_effects` and `players_items`. A life
 * deficit belongs there and SHOULD be wiped; a level must not.
 *
 * `pi` belongs here too: `Player::put_xp()` mints it in the same statement as
 * experience, capped at the season's XP ceiling, and it buys characteristic
 * upgrades. It is not a purse — gold is an item — but the currency progression
 * itself produces.
 */
interface ProgressesInterface
{
    public function getXp(): int;

    public function setXp(int $xp): self;

    public function addXp(int $amount): self;

    /** The level, as the game names it. */
    public function getRank(): int;

    public function setRank(int $rank): self;

    /** Unspent points a level grants. */
    public function getBonusPoints(): int;

    public function setBonusPoints(int $bonusPoints): self;

    /** What experience mints and characteristic upgrades spend. */
    public function getPi(): int;

    public function setPi(int $pi): self;

    public function addPi(int $amount): self;
}
