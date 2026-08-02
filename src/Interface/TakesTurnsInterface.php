<?php

namespace App\Interface;

/**
 * Something that gets a turn: a delay before it may act again, and a memory of
 * when it last did.
 *
 * Held by `Character` today, and by nothing else — which made "gets a turn"
 * look like a property of being a character. It is not: a playable building
 * takes turns, spends action points and moves if it is a siege engine, without
 * ever having an account (docs/design-playable-buildings.md §2).
 *
 * So the contract is named before it is shared. A reader that asks "when may
 * this act?" types on this interface, and stops asking what branch of the tree
 * answered.
 *
 * Deliberately NOT here: the action pool itself (A, MVT), which lives in the
 * `players.turn` JSON and is still read through the legacy player. Naming a
 * contract over state that has not been extracted yet would describe a design
 * rather than the code.
 */
interface TakesTurnsInterface
{
    /** Unix time at which the next turn falls due; 0 = due now. */
    public function getNextTurnTime(): int;

    public function setNextTurnTime(int $nextTurnTime): self;

    /** True when this turn's schedule was already pushed once. */
    public function isNextTurnRescheduled(): bool;

    public function setNextTurnRescheduled(bool $nextTurnRescheduled): self;

    /** Unix time of the last action taken, 0 if it never acted. */
    public function getLastActionTime(): int;

    public function setLastActionTime(int $lastActionTime): self;
}
