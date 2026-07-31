<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Une plante : fleur, herbe, ronce. On lui marche dessus sans la prendre.
 *
 * Elle se récolte comme une ressource — d'où {@see Harvestable}, partagé — mais
 * elle ne lui ressemble en rien d'autre : une ressource bloque le pas et se
 * frappe, une plante se traverse et se ramasse, comme un objet posé au sol.
 * Faire descendre l'une de l'autre aurait imposé à la fleur les mœurs du
 * rocher ; c'est exactement pour ce cas que la capacité est un contrat et non
 * une place dans l'arbre.
 *
 * Elle reste une chose POSÉE, donc une {@see StructureType} : elle occupe une
 * case et porte une emprise. Ce qu'elle ne fait pas, c'est bloquer — et cela
 * vient de son TYPE (`blocks_passage = 0`), pas d'un rôle de case.
 *
 * Ses cases prendront donc `part`, où le type tranche, et surtout PAS `cover` :
 * `cover` est un ordre de dessin, la portion d'un décor peinte AU-DESSUS du
 * personnage — on passe derrière, on tire par-dessus. Une fleur se marche
 * DESSUS ; on ne se cache pas dans les fleurs.
 *
 * Déclarée AVANT qu'une seule ligne ne porte le type, et cet ordre a été payé :
 * `scenery` avait rejoint la table après ses lignes, et toute recherche par le
 * tronc rendait `null` pour un décor jusqu'à ce que quelqu'un le remarque.
 */
#[ORM\Entity]
class PlantType extends StructureType implements Harvestable
{
    use HarvestableFields;

    public function familyKey(): string
    {
        return self::FAMILY_PLANT;
    }
}
