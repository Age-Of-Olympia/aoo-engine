<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use Classes\Db;
use Classes\Player;
use Classes\View;

class PlantsService
{


    public static function getTriggerGrow(): Object
    {
        //on recupère les triggers de type "grow" pour lesquels il n'y a pas de plants correspondant
        $sql = "
        SELECT
        t.id AS id,
        t.params,
        t.coords_id,
        c.z AS z
        FROM
        map_triggers t

        INNER JOIN
        coords c
        ON
        t.coords_id = c.id
        LEFT JOIN map_plants p 
        ON p.coords_id = c.id
        WHERE
        t.name = 'grow'
        and p.id is null;
        ";

        $db = new Db();

        $res = $db->exe($sql);

        return $res;
    }


    public static function growSeed($plante, $coords)
    {
        //chaque plante a un pourcentage de chance de pousser (dans constant.php)

        if(!empty(GROW_RATE[$plante])){

            $growTo = GROW_RATE[$plante];

        }

        //chance de 1/growTo
        if(AUTO_GROW || rand(1,$growTo) == 1)
        {

            $values = array(
                'name'=>$plante,
                'coords_id'=>$coords
            );
            
            $db = new Db();
            $db->insert('map_plants', $values);
            
        }
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

    public static function regrowResources(array &$resourcesId): void
    {
        if(empty($resourcesId)) {
            return;
        }

        $sql = '
        UPDATE map_walls
        SET damages=-1
        WHERE 
        id IN('. implode(',', $resourcesId) .')
        ';

        $db = new Db();
        $res = $db->exe($sql);
    }


}