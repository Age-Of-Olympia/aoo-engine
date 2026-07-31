<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Une ressource : arbre, pierre, tourbe. Elle bloque le pas et se frappe.
 *
 * Le nom dit la FAMILLE, comme son discriminant `resource` — et non ce qu'elle
 * sait faire. Elle s'appelait `HarvestableType`, ce qui confondait les deux et
 * serait devenu faux : les plantes se récolteront aussi sans être des
 * ressources, étant marchables et ramassables.
 *
 * Ce qu'elle sait faire est donc dit par {@see Harvestable}, un contrat que
 * d'autres familles rempliront sans descendre de celle-ci.
 *
 * Le rendement, lui, vivait sur le tronc, vide pour 86 lignes sur 128 : on
 * pouvait demander le sien à une race de nain, qui répondait `null`. La
 * question ne se pose plus — elle ne compile pas.
 */
#[ORM\Entity]
class ResourceType extends StructureType implements Harvestable
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
