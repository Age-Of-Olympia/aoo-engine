<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Spell learnable by this race (formerly the `spells` list of
 * datas/[public|private]/races/<race>.json). Gates merchant spell listings and whether an
 * item's embedded spell is castable by the player.
 */
#[ORM\Entity]
#[ORM\Table(name: "race_spells")]
#[ORM\UniqueConstraint(name: "UNIQ_race_spells_race_name", columns: ["race_id", "name"])]
/* The association lives on the mapped superclass, shared by two subclasses:
 * only the child can name its own inverse side (race -> spells). */
#[ORM\AssociationOverrides([new ORM\AssociationOverride(name: "race", inversedBy: "spells")])]
class RaceSpell extends RaceNameListEntry
{
}
