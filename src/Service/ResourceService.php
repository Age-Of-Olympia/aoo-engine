<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use Db;
use Player;
use View;

class ResourceService
{

    public static function findResourcesAround(Player $player): mixed
    {
        $biomes = array();
        $coords = $player->getCoords();
        $planJson = json()->decode('plans', $coords->plan);

        if(!empty($planJson->biomes)){
            foreach($planJson->biomes as $e){
                $biomes[$e->wall] = $e->ressource;
            }
        }

        $coordsArround = View::get_coords_id_arround($coords, $p=1);

        $sql = '
        SELECT
        COUNT(*) AS max,
        name
        FROM
        map_walls
        WHERE
        coords_id IN('. implode(',', $coordsArround) .')
        AND
        name IN ("'. implode('","', array_keys($biomes)) .'")
        AND
        damages=0
        GROUP BY
        name
        ';

        $db = new Db();
        $res = $db->exe($sql);

        return $res;
    }

    public static function getResourcesAround(Player $player): mixed
    {
        $biomes = array();
        $coords = $player->getCoords();
        $planJson = json()->decode('plans', $coords->plan);

        if(!empty($planJson->biomes)){
            foreach($planJson->biomes as $e){
                $biomes[$e->wall] = $e->ressource;
            }
        }

        $coordsArround = View::get_coords_id_arround($coords, $p=1);

        $sql = '
        SELECT
        id,
        name
        FROM
        map_walls
        WHERE
        coords_id IN('. implode(',', $coordsArround) .')
        AND
        name IN ("'. implode('","', array_keys($biomes)) .'")
        AND
        damages=0
        GROUP BY
        name
        ';

        $db = new Db();
        $res = $db->exe($sql);

        return $res;
    }

    public static function exhaustResources(array $resourcesId): void
    {

        $sql = '
        UPDATE map_walls
        SET damages=-1
        WHERE 
        id IN('. implode(',', $resourcesId) .')
        ';

        $db = new Db();
        $res = $db->exe($sql);
    }

    public static function replenishResources(array $resourcesId): void
    {

        $sql = '
        UPDATE map_walls
        SET damages=0
        WHERE 
        id IN('. implode(',', $resourcesId) .')
        ';

        $db = new Db();
        $res = $db->exe($sql);
    }

}