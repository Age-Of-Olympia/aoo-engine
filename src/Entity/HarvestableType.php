<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Un type récoltable : arbre, pierre, tourbe.
 *
 * La seule famille à porter un rendement — ce qu'elle donne, à quel rythme
 * elle s'épuise et repousse. Poser un type suffit à ce qu'il rende quelque
 * chose ; un plan ne fait que dévier.
 *
 * Ces trois colonnes vivaient sur le tronc, vides pour 86 lignes sur 128 : on
 * pouvait demander son rendement à une race de nain, qui répondait `null`.
 * Ici, la question ne se pose plus — elle ne compile pas.
 */
#[ORM\Entity]
class HarvestableType extends Race
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

    public function familyKey(): string
    {
        return self::FAMILY_RESOURCE;
    }

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
