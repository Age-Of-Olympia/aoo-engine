<?php

namespace App\Enum;

/**
 * The two branches of the GameEntity tree, as data
 * (docs/design-buildings-entities.md §4.3/§4.4):
 *
 *   Character — real players, tutorial players, NPCs
 *   Structure — buildings, unique objects, scenery
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
        // null = legacy rows created before the discriminator existed.
        // Any OTHER unknown value throws : un sixième player_type ajouté
        // sans étendre ce mapping doit échouer bruyamment, pas passer
        // silencieusement toutes les portes « character ».
        return match ($playerType) {
            'building', 'unique', 'scenery' => self::Structure,
            'real', 'tutorial', 'npc', null => self::Character,
            default => throw new \ValueError("player_type inconnu : « {$playerType} » — étendre EntityCategory::fromPlayerType."),
        };
    }

    /**
     * Part of the scenery: always seen, never an interlocutor. Callers used
     * to spell out `['building', 'unique']`, which is how `scenery` was
     * missed in four places at once.
     */
    public function isStructure(): bool
    {
        return $this === self::Structure;
    }

    /** @return array<string, string> value => French label, for admin selects */
    public static function options(): array
    {
        return [
            self::Character->value => 'Personnage',
            self::Structure->value => 'Structure',
        ];
    }

    /**
     * Acteur SOCIAL : peut échanger des missives, compter dans une
     * faction, apparaître dans les surfaces de personnages. Un bâtiment
     * ou un objet unique porte une faction et des options (isMerchant…)
     * mais n'est pas un interlocuteur.
     */
    public function isSocialActor(): bool
    {
        return $this === self::Character;
    }
}
