<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Un type récoltable : arbre, pierre, tourbe.
 *
 * La seule famille à porter un rendement — ce qu'elle donne, à quel rythme
 * elle s'épuise et repousse. Poser un type suffit à ce qu'il rende quelque
 * chose ; un plan ne fait que dévier.
 */
#[ORM\Entity]
class HarvestableType extends Race
{
    public function familyKey(): string
    {
        return self::FAMILY_RESOURCE;
    }
}
