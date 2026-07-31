<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Un type de décor : dessiné, pas incarné.
 *
 * Ses cases portent le rôle `cover`, qui n'arrête ni le pas ni les tirs, et
 * son sprite vit dans img/foregrounds — deux particularités qui ne concernent
 * que cette famille.
 */
#[ORM\Entity]
class SceneryType extends StructureType
{
    public function familyKey(): string
    {
        return self::FAMILY_SCENERY;
    }

    /**
     * Un décor est POSÉ par quelqu'un : statue, mobilier, clôture. Ce qui a été
     * dressé s'entretient, au même titre qu'un édifice.
     *
     * Ce qui POUSSE ou GÎT là — plantes, ressources — ne se répare pas : leur
     * cycle est l'épuisement puis la repousse, pas l'entretien.
     */
    protected function repairableByDefault(): bool
    {
        return true;
    }
}
