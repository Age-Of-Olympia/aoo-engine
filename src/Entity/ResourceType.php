<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Une ressource : arbre, pierre, tourbe. Elle bloque le pas et se frappe.
 *
 * Le nom dit la FAMILLE, comme son discriminant `resource` — et non ce qu'elle
 * sait faire. Elle s'appelait `HarvestableType`, ce qui confondait les deux et
 * serait devenu faux : les plantes se récoltent aussi sans être des
 * ressources, étant marchables et ramassables.
 *
 * Ce qu'elle sait faire est dit par {@see Harvestable} et porté par
 * {@see HarvestableFields}, que {@see PlantType} partage sans descendre d'ici.
 *
 * Le rendement, lui, vivait sur le tronc, vide pour 86 lignes sur 128 : on
 * pouvait demander le sien à une race de nain, qui répondait `null`. La
 * question ne se pose plus — elle ne compile pas.
 */
#[ORM\Entity]
class ResourceType extends StructureType implements Harvestable
{
    use HarvestableFields;

    public function familyKey(): string
    {
        return self::FAMILY_RESOURCE;
    }
}
