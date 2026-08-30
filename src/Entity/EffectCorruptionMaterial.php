<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * One material a corruption effect can break (formerly one entry of the
 * ITEM_CORRUPTIONS constant). Names reference items.name ('bronze',
 * 'cuir'…) without FK, same contract as the race name lists.
 */
#[ORM\Entity]
#[ORM\Table(name: "effect_corruption_materials")]
#[ORM\UniqueConstraint(name: "UNIQ_effect_corruption_materials_effect_name", columns: ["effect_id", "name"])]
/* The association lives on the mapped superclass, shared by two subclasses:
 * only the child can name its own inverse side (effect -> corruptionMaterials). */
#[ORM\AssociationOverrides([new ORM\AssociationOverride(name: "effect", inversedBy: "corruptionMaterials")])]
class EffectCorruptionMaterial extends EffectNameListEntry
{
}
