<?php

namespace App\Action;

use App\Entity\Action;
use Doctrine\ORM\Mapping as ORM;

/**
 * A free gesture on the world — turning a lock, and whatever joins it
 * later. No cost by nature, and no action_type_xp rule may ever bind
 * to this type: an unlimited gesture that minted experience would be
 * a pump.
 */
#[ORM\Entity]
class GestureAction extends Action
{
}
