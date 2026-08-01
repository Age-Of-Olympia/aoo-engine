<?php

namespace App\Interface;

/**
 * What a type obstructs once placed. The two answers are independent: ten of
 * the fifty-three wall types stop the step but not the arrow.
 *
 * Implemented by both catalogues; only meaningful for a placed entity, since
 * `slot` rules out dropped loot before the type is consulted.
 */
interface ObstructsInterface
{
    public function blocksPassage(): bool;

    public function blocksProjectiles(): bool;
}
