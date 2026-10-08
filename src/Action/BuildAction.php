<?php

namespace App\Action;

use App\Entity\Action;
use Doctrine\ORM\Mapping as ORM;

/**
 * STI type of the generic construire action (key 'build'): the item picked
 * at execution (ItemPick) is raised on an adjacent tile. Not a buff — it is
 * neither a learnable skill nor an item-granted spell.
 */
#[ORM\Entity]
class BuildAction extends Action
{
}
