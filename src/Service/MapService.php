<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Entity\Race;
use Db;

class MapService
{
    public function getTileTypeAtCoord(string $name, int $coordId) {
        $sql = 'SELECT COUNT(*) AS n FROM map_'.$name.' WHERE coords_id = ?';

        $db = new Db();
        $res = $db->exe($sql, $coordId);

        return $res->fetch_object();
    }
}
