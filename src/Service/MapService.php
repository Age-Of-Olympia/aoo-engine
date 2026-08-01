<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Entity\Race;
use Classes\Db;

class MapService
{
    public function getTileTypeAtCoord(string $name, int $coordId) {
        // $name names a map_<name> table and is interpolated into the query, so
        // guard it to a bare table-suffix token — it reaches here from action
        // config, not a bound parameter, so it must carry no SQL.
        if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException("Type de tuile invalide : {$name}.");
        }

        $sql = 'SELECT COUNT(*) AS n FROM map_'.$name.' WHERE coords_id = ?';

        $db = new Db();
        $res = $db->exe($sql, $coordId);

        return $res->fetch_object();
    }
}
