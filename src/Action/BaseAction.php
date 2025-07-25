<?php

namespace App\Action;

use App\Entity\Action;
use App\Interface\ActorInterface;
use Classes\Player as Player;
use Doctrine\ORM\Mapping as ORM;

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

  public function calculateXp(bool $success, ActorInterface $actor, ActorInterface $target): array
  {
    $xpResultsArray["actor"] = 0;
    $xpResultsArray["target"] = 0;
    return $xpResultsArray;
  }
}
