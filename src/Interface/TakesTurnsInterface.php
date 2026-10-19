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
 * So the capability is named before it is shared: a gate asks "does this take
 * turns?" on this interface, and stops asking what branch of the tree answered.
 *
 * A marker: the state lives in the `turns` satellite, written and read through
 * {@see \App\Service\TurnService}, not mapped on the entity.
 */
interface TakesTurnsInterface
{
}
