<?php

namespace App\Action;

use App\Entity\Action;
use Doctrine\ORM\Mapping as ORM;
use Player;

#[ORM\Entity]
class BaseAction extends Action
{
  public function getLogMessages(Player $actor, Player $target): array
  {
    $infosArray = array();
    $infosArray["actor"] = ""; 
    $infosArray["target"] = "";
    return $infosArray;
  }

  public function calculateXp(bool $success, Player $actor, Player $target): array
  {
    $xpResultsArray["actor"] = 0;
    $xpResultsArray["target"] = 0;
    return $xpResultsArray;
  }
}
