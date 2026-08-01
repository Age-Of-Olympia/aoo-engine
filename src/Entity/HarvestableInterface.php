<?php

namespace App\Entity;

/**
 * Ce qui se récolte : un contrat, pas une place dans l'arbre.
 *
 * `ResourceType` le remplit aujourd'hui. Les PLANTES le rempliront demain — et
 * c'est toute la raison d'être de cette interface : une plante se récolte comme
 * un arbre, mais elle est marchable et se ramasse, là où une ressource bloque
 * le pas et se frappe. Faire descendre l'une de l'autre imposerait à la fleur
 * les mœurs du rocher.
 *
 * L'héritage dit ce qu'un type EST — sa famille, son discriminant. Une
 * interface dit ce qu'il SAIT FAIRE, et cela traverse les familles sans les
 * ranger l'une sous l'autre.
 *
 * Un appelant qui veut un rendement demande donc `HarvestableInterface`, jamais une
 * classe : le jour où les plantes arrivent, il n'a rien à changer.
 *
 * Pas de trait derrière, pour l'instant : avec un seul implémenteur il n'y
 * aurait rien à mutualiser. Il viendra avec le deuxième, et l'extraction sera
 * mécanique — l'interface, elle, se gagne dès maintenant.
 */
interface HarvestableInterface
{
    /** L'objet que ce type rend ; '' = il ne rend rien. */
    public function getHarvestItem(): string;

    public function setHarvestItem(string $item): self;

    /** Chance sur cent de tarir à la récolte ; null = jamais. */
    public function getHarvestExhaust(): ?int;

    public function setHarvestExhaust(?int $exhaust): self;

    /** Chance sur mille de repousser, par passage du cron ; null = jamais. */
    public function getHarvestRegrow(): ?int;

    public function setHarvestRegrow(?int $regrow): self;
}
