<?php

namespace App\Action;

use App\Entity\Action;
use Doctrine\ORM\Mapping as ORM;

/**
 * A gesture on the world — turning a lock, writing on a thing. Its
 * cost, if any, comes from its conditions (écrire: 1 A); no
 * action_type_xp rule may ever bind to this type: a repeatable gesture
 * that minted experience would be a pump.
 */
#[ORM\Entity]
class GestureAction extends Action
{
}
