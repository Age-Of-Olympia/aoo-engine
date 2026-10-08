<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Un type de chose bâtie : édifice à porte, ou obstacle construit.
 *
 * `structure_nature` garde ici tout son sens — c'est ce qui distingue les deux
 * — alors qu'elle n'en avait aucun pour un personnage.
 */
#[ORM\Entity]
class BuildingType extends StructureType
{
    public function familyKey(): string
    {
        return self::FAMILY_BUILDING;
    }
}
