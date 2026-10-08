<?php

namespace App\Action;

use App\Entity\Action;
use Doctrine\ORM\Mapping as ORM;

/**
 * STI type of the generic consommer action (key 'consume'): the item picked
 * at execution (ItemPick) is used up and its payload applied. Not a buff —
 * it is neither a learnable skill nor an item-granted spell.
 */
#[ORM\Entity]
class ConsumeAction extends Action
{
}
