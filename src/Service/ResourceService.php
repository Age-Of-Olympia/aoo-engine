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

        $coordsArround = null;
        $coordsIdArround=array();
        
        View::get_coords_id_arround($coordsArround,$coordsIdArround,$coords, p:1);

        $sql = '
        SELECT
        COUNT(*) AS max,
        name
        FROM
        map_walls
        WHERE
        coords_id IN('. implode(',', $coordsIdArround) .')
        AND
        name IN ("'. implode('","', array_keys($biomes)) .'")
        AND
        damages=-1
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

        $coordsArround = null;
        $coordsIdArround=array();
        
        View::get_coords_id_arround($coordsArround,$coordsIdArround,$coords, p:1);


        $sql = '
        SELECT
        id,
        name
        FROM
        map_walls
        WHERE
        coords_id IN('. implode(',', $coordsIdArround) .')
        AND
        name IN ("'. implode('","', array_keys($biomes)) .'")
        AND
        damages=-1
        ';

        $db = new Db();
        $res = $db->exe($sql);

        return $res;
    }

    public static function exhaustResources(array $resourcesId): void
    {

        $sql = '
        UPDATE map_walls
        SET damages=-2
        WHERE 
        id IN('. implode(',', $resourcesId) .')
        ';

        $db = new Db();
        $res = $db->exe($sql);
    }

    public static function regrowResources(array $resourcesId): void
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

    public static function createExhaustArray($planJson, array &$resourcesIdArray, $row): void
    {

        foreach($planJson->biomes as $e){
                if($e->wall == $row->name){
                    if($e->exhaust < rand(1, 100))
                        $resourcesIdArray[] = $row->id;
                    break;
                }
            }
    }

    public static function createRegrowArray($biome, array &$resourcesIdArray, $row): void
    {
        if ($biome->regrow < rand(1, 1000))
            $resourcesIdArray[] = $row->id;
    }

}