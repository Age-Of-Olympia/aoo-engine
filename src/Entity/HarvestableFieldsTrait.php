<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Le rendement, tel que le portent ceux qui savent se récolter.
 *
 * L'implémentation partagée derrière {@see HarvestableInterface}. Elle naît maintenant
 * et pas plus tôt : à un seul implémenteur il n'y avait rien à mutualiser,
 * seulement un détour à ajouter. Les plantes en font le deuxième, et le trait
 * cesse d'être spéculatif.
 *
 * Les trois colonnes sont MAPPÉES ici, et elles atterrissent dans l'unique
 * table `races` pour les deux familles qui utilisent ce trait. Doctrine
 * l'accepte — vérifié plutôt que supposé, sur une hiérarchie jetable :
 * métadonnées chargées, schéma créé, aller-retour écriture/lecture réussi.
 * C'est ce qui permet à une capacité de traverser des familles qui ne forment
 * pas un sous-arbre.
 */
trait HarvestableFieldsTrait
{
    /**
     * Ce que ce type rend à la récolte, et à quel rythme il s'épuise et
     * repousse.
     *
     * Porté par le TYPE : poser un récoltable neuf suffit à ce qu'il rende
     * quelque chose. `race_harvest` ne sert plus qu'à faire dévier UN plan —
     * une ligne y est une exception, pas un prérequis.
     */
    #[ORM\Column(type: "string", length: 255, name: "harvest_item", nullable: true)]
    private ?string $harvestItem = null;

    #[ORM\Column(type: "smallint", name: "harvest_exhaust", nullable: true)]
    private ?int $harvestExhaust = null;

    #[ORM\Column(type: "smallint", name: "harvest_regrow", nullable: true)]
    private ?int $harvestRegrow = null;

    public function getHarvestItem(): string
    {
        return (string) $this->harvestItem;
    }

    /** Vide = ce type ne rend rien : il faudra une ligne par plan, ou rien. */
    public function setHarvestItem(string $item): self
    {
        $this->harvestItem = trim($item) === '' ? null : trim($item);

        return $this;
    }

    public function getHarvestExhaust(): ?int
    {
        return $this->harvestExhaust;
    }

    public function setHarvestExhaust(?int $exhaust): self
    {
        $this->harvestExhaust = $exhaust;

        return $this;
    }

    public function getHarvestRegrow(): ?int
    {
        return $this->harvestRegrow;
    }

    public function setHarvestRegrow(?int $regrow): self
    {
        $this->harvestRegrow = $regrow;

        return $this;
    }
}
