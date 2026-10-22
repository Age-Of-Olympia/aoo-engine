<?php

namespace App\Action;

use App\Entity\Action;
use Doctrine\ORM\Mapping as ORM;

/** Opens a character exchange with the selected character. */
#[ORM\Entity]
class GiveAction extends Action
{
}
