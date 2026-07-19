<?php

namespace App\Action;

use Doctrine\ORM\Mapping as ORM;

/**
 * Type STI du creusement (clé 'dig'), enfant de search : l'XP hérite de
 * la règle search (1 = XP_PER_MINE), mais l'événement a son propre
 * gabarit action_type_logs — « fouillé les alentours » ne raconte pas
 * un tunnel.
 */
#[ORM\Entity]
class DigAction extends SearchAction
{
}
