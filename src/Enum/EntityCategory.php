<?php

namespace App\Enum;

/**
 * The two branches of the GameEntity tree, as data
 * (docs/design-buildings-entities.md §4.3/§4.4):
 *
 *   Character — real players, tutorial players, NPCs
 *   Structure — buildings, unique objects
 *
 * Everything data-driven that discriminates by branch (TargetType
 * condition, effect application gates, races.kind) speaks in these two
 * values, mapped from the players.player_type discriminator here — one
 * place, so a future discriminator never gets forgotten in a copy of
 * the mapping.
 */
enum EntityCategory: string
{
    case Character = 'character';
    case Structure = 'structure';

    public static function fromPlayerType(?string $playerType): self
    {
        return match ($playerType) {
            'building', 'unique' => self::Structure,
            default => self::Character,
        };
    }

    /** @return array<string, string> value => French label, for admin selects */
    public static function options(): array
    {
        return [
            self::Character->value => 'Personnage',
            self::Structure->value => 'Structure',
        ];
    }
}
