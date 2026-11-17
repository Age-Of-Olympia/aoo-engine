<?php

namespace App\Action;

/** What a death resolved by the executor did; a view tells it to the player. */
final class DeathReport
{
    public const KILLED = 'killed';
    public const DESTROYED = 'destroyed';
    public const SUCCUMBED = 'succumbed';

    /** @param list<array{name: string, xp: int, bonus: bool}> $xpGains who earned what from the kill, in sharing order */
    public function __construct(
        public readonly string $outcome,
        public readonly int $xpShared = 0,
        public readonly bool $victimInactive = false,
        public readonly array $xpGains = [],
    ) {
    }
}
