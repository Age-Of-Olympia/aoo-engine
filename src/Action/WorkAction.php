<?php

namespace App\Action;

use App\Entity\Action;
use Doctrine\ORM\Mapping as ORM;

/**
 * STI type of the construction site actions (key 'work'). XP comes from the
 * action_type_xp row seeded at the reparer rate — a data knob, not a
 * rule in code.
 */
#[ORM\Entity]
class WorkAction extends Action
{
}
