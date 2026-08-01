<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Une plante : fleur, herbe, ronce. On lui marche dessus sans la prendre.
 *
 * Elle se récolte comme une ressource — d'où {@see HarvestableInterface}, partagé — mais
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
class PlantType extends StructureType implements HarvestableInterface
{
    use HarvestableFieldsTrait;

    /**
     * Combien cette plante rend, à la cueillette.
     *
     * Sur la plante et non sur le trait partagé : une ressource ne décide pas
     * sa quantité de la même façon — `fouiller` est une action de ZONE, et ce
     * qu'elle rend dépend du nombre de voisines. La capacité commune est de
     * rendre quelque chose ; combien, chaque famille le dit à sa manière.
     */
    #[ORM\Column(type: "smallint", name: "harvest_min", nullable: true)]
    private ?int $harvestMin = null;

    #[ORM\Column(type: "smallint", name: "harvest_max", nullable: true)]
    private ?int $harvestMax = null;

    /** Ce que le code tirait avant que la plante ait son mot à dire. */
    public const DEFAULT_MIN = 1;
    public const DEFAULT_MAX = 3;

    public function familyKey(): string
    {
        return self::FAMILY_PLANT;
    }

    public function getHarvestMin(): int
    {
        return $this->harvestMin ?? self::DEFAULT_MIN;
    }

    public function getHarvestMax(): int
    {
        /* Un maximum sous le minimum ne veut rien dire : on rend le minimum
         * plutôt qu'un intervalle vide, et l'écran empêche d'en arriver là. */
        return max($this->getHarvestMin(), $this->harvestMax ?? self::DEFAULT_MAX);
    }

    public function setHarvestMin(?int $min): self
    {
        $this->harvestMin = $min === null ? null : max(1, $min);

        return $this;
    }

    public function setHarvestMax(?int $max): self
    {
        $this->harvestMax = $max === null ? null : max(1, $max);

        return $this;
    }
}
