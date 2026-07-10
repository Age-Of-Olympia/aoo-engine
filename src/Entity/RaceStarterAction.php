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
class RaceStarterAction extends RaceNameListEntry
{
}
