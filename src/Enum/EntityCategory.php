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
            /* `plant` manquait, et l'avertissement ci-dessus a tenu parole :
             * cliquer une case fleurie levait, observe.php répondait 500 et le
             * panneau ne bougeait pas — un clic sans effet, sans message. Une
             * plante est POSÉE comme le reste (PlantType étend StructureType),
             * elle n'est pas un interlocuteur. */
            /* An item exemplar is a Structure for the same reasons a wall is:
             * no dodge, no malus, and destruction shelves it rather than
             * sending it to the enfers. Whether it can be hit at all follows
             * from standing on a cell, not from the discriminator. */
            'building', 'scenery', 'resource', 'plant', 'item' => self::Structure,
            'real', 'tutorial', 'npc', null => self::Character,
            default => throw new \ValueError("player_type inconnu : « {$playerType} » — étendre EntityCategory::fromPlayerType."),
        };
    }

    /**
     * Part of the scenery: always seen, never an interlocutor. Ask this rather
     * than listing discriminators, or the next family added is missed.
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
     * Structure families by discriminator, for gates finer than the branch —
     * a rule that holds for a building but not for a tree cannot be expressed
     * with `structure` alone.
     *
     * These keys ARE the structure discriminators of {@see \App\Entity\GameEntity};
     * adding one without listing it here fails EntityFamiliesVocabularyTest.
     *
     * @return array<string, string> player_type => label
     */
    public static function structureFamilies(): array
    {
        return [
            'building' => 'Bâtiment',
            'scenery'  => 'Décor',
            'resource' => 'Ressource',
            'plant'    => 'Plante',
            'item'     => 'Objet posé',
        ];
    }

    /**
     * Acteur SOCIAL : peut échanger des missives, compter dans une
     * faction, apparaître dans les surfaces de personnages. Un bâtiment
     * ou un objet unique porte une faction et un dialogue (qui fait ses
     * comptoirs) mais n'est pas un interlocuteur.
     */
    public function isSocialActor(): bool
    {
        return $this === self::Character;
    }
}
