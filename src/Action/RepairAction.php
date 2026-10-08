<?php

namespace App\Action;

use App\Entity\Action;
use Doctrine\ORM\Mapping as ORM;

/**
 * STI type of reparer (key 'repair'): mending a building, paid in
 * resources or gold like the atelier's repair. Not a heal — no spell, no
 * heal group, its own event and messages.
 */
#[ORM\Entity]
class RepairAction extends Action
{
}
