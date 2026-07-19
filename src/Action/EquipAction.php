<?php

namespace App\Action;

use App\Entity\Action;
use Doctrine\ORM\Mapping as ORM;

/**
 * Type STI des actions d'équipement (clé 'equip'). Sans ligne
 * action_type_xp : équiper/déséquiper ne rapporte AUCUNE XP — pas de
 * fermier de garde-robe (règle ajustable en données via l'admin
 * « Défauts par type »).
 */
#[ORM\Entity]
class EquipAction extends Action
{
}
