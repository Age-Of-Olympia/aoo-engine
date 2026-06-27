<?php

namespace App\Action\Schema;

enum FieldType: string
{
    case TRAIT = 'trait';
    case INT = 'int';
    case BOOL = 'bool';
    case STRING = 'string';
    case ENUM = 'enum';
    case TRAIT_OR_INT = 'trait_or_int';
    case LIST = 'list';

    // Catalog-backed selects: options come from OptionCatalog (real game values).
    case EFFECT = 'effect';
    case PASSIVE = 'passive';
    case WEAPON_TYPE = 'weapon_type';
    case EMPLACEMENT = 'emplacement';
    case MATERIAL = 'material';
    case ITEM = 'item';
    case ACTION = 'action';

    /** Whether this type's options are sourced from OptionCatalog. */
    public function isCatalog(): bool
    {
        return match ($this) {
            self::EFFECT, self::PASSIVE, self::WEAPON_TYPE, self::EMPLACEMENT, self::MATERIAL, self::ITEM, self::ACTION => true,
            default => false,
        };
    }
}
