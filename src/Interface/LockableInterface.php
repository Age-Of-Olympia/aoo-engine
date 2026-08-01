<?php

namespace App\Interface;

/**
 * A TYPE that can be shut — and therefore locked by whoever owns it.
 *
 * A chest and a door can; a wall cannot. Implemented by both catalogues, so a
 * doorway built into a rampart and a gate carried in a bag answer the same
 * question, and the caller never learns which table replied. Same seam as
 * {@see OwnsCaracsInterface}.
 *
 * It is deliberately NOT derived from `structure_nature`: that column sorts a
 * real building from a built object, and every chest in the game sits on the
 * `obstacle` side with the walls. Two different questions.
 *
 * What being shut DOES is not asked here — it follows from the other things the
 * entity is. A barrier blocks, a container withholds its contents, a shop stops
 * serving. That is why "door" is not a family: it is anything shuttable that
 * also blocks passage.
 */
interface LockableInterface
{
    /** Can a thing of this type be shut at all? */
    public function isLockable(): bool;
}
