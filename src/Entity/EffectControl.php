<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * One effect this effect cancels when applied (formerly the 1:1
 * effects.controls column, itself heir to the ELE_CONTROLS constant —
 * a list since certain effects cancel several others).
 */
#[ORM\Entity]
#[ORM\Table(name: "effect_controls")]
#[ORM\UniqueConstraint(name: "UNIQ_effect_controls_effect_name", columns: ["effect_id", "name"])]
/* The association lives on the mapped superclass, shared by two subclasses:
 * only the child can name its own inverse side (effect -> controls). */
#[ORM\AssociationOverrides([new ORM\AssociationOverride(name: "effect", inversedBy: "controls")])]
class EffectControl extends EffectNameListEntry
{
}
