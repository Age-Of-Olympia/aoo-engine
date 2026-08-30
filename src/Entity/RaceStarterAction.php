<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Action granted to a player of this race at creation (the starter pack,
 * formerly the `actions` list of datas/[public|private]/races/<race>.json).
 */
#[ORM\Entity]
#[ORM\Table(name: "race_starter_actions")]
#[ORM\UniqueConstraint(name: "UNIQ_race_starter_actions_race_name", columns: ["race_id", "name"])]
/* The association lives on the mapped superclass, shared by two subclasses:
 * only the child can name its own inverse side (race -> starterActions). */
#[ORM\AssociationOverrides([new ORM\AssociationOverride(name: "race", inversedBy: "starterActions")])]
class RaceStarterAction extends RaceNameListEntry
{
}
