<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A road type: ground one walks on, not something standing on the cell.
 *
 * Passage and projectiles are the family's answer, not the row's: a road that
 * blocks is not a road, whatever its columns say.
 */
#[ORM\Entity]
class RouteType extends StructureType
{
    public const IMAGE_DIR = 'routes';

    public function familyKey(): string
    {
        return self::FAMILY_ROUTE;
    }

    public function imageDir(): string
    {
        return self::IMAGE_DIR;
    }

    public function blocksPassage(): bool
    {
        return false;
    }

    public function blocksProjectiles(): bool
    {
        return false;
    }

    /** Roads are laid by someone, so they are maintained. */
    protected function repairableByDefault(): bool
    {
        return true;
    }
}
