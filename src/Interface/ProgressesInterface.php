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
 * Deliberately NOT here: `pi`, which `Player::put_xp()` grants alongside
 * experience but which is also spent as currency. Whether a playable building
 * has a purse is its own question, and answering it by accident — through a
 * getter added for symmetry — is how it would get answered wrong.
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
}
