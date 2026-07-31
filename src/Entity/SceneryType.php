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
class SceneryType extends Race
{
    public function familyKey(): string
    {
        return self::FAMILY_SCENERY;
    }
}
