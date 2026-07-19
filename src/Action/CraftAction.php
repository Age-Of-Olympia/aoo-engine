<?php

namespace App\Action;

use App\Entity\Action;
use Doctrine\ORM\Mapping as ORM;

/**
 * Type STI des actions d'artisanat (clé 'craft'). Sans ligne
 * action_type_xp : fabriquer ne rapporte aucune XP — parité avec le
 * craft historique (règle ajustable en données via l'admin « Défauts
 * par type »).
 */
#[ORM\Entity]
class CraftAction extends Action
{
}
