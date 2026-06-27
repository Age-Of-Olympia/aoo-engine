<?php

namespace App\Action;

use App\Entity\Action;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class StealAction extends Action
{
    public function hideOnSuccess(): bool
    {
        return true;
    }

    public function activateAntiBerserk(): bool
    {
        return true;
    }
}
