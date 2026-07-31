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

    /**
     * Ce qui a été BÂTI se répare : c'est la seule famille dont l'entretien
     * est un geste du jeu. Un rocher ne se rafistole pas, un décor non plus,
     * et une fleur encore moins — pour eux, la case reste décochée.
     */
    protected function repairableByDefault(): bool
    {
        return true;
    }
}
