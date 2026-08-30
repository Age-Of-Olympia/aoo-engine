<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Entity\Race;
use Classes\Db;

class MapService
{
    /**
     * Is there a $name on this cell? Kept returning an object with `n` so
     * `Player::isOnTileType` reads the same as ever.
     *
     * The answer moved to GroundLayerService, which knows that some layers
     * are entity families now — a road has been one since roads gained life
     * and an owner — and still reads `map_<name>` for those that are not.
     */
    public function getTileTypeAtCoord(string $name, int $coordId) {
        return (object) [
            'n' => (new \App\Service\Map\GroundLayerService())->hasAt($name, $coordId) ? 1 : 0,
        ];
    }
}
