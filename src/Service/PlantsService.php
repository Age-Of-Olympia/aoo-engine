<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use Classes\Db;
use Classes\Player;
use Classes\View;

class PlantsService
{
    /** Dé injectable — même point d'injection que ResourceService. */
    private static ?\Classes\Dice $dice = null;

    public static function setDiceForTests(?\Classes\Dice $dice): void
    {
        self::$dice = $dice;
    }

    /** Un dé à $sides faces, une fois. */
    private static function roll(int $sides): int
    {
        $dice = self::$dice ?? new \Classes\Dice(max(1, $sides));

        return (int) $dice->roll(1)[0];
    }


    public static function getTriggerGrow(): Object
    {
        //on recupère les triggers de type "grow" pour lesquels il n'y a pas de plants correspondant
        //(ni élément ni route : une plante n'y pousse pas)
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
        LEFT JOIN map_elements e
        ON e.coords_id = c.id
        LEFT JOIN map_routes r
        ON r.coords_id = c.id
        WHERE
        t.name = 'grow'
        and p.id is null
        and e.id is null
        and r.id is null;
        ";

        $db = new Db();

        $res = $db->exe($sql);

        return $res;
    }


    public static function growSeed($plante, $coords)
    {
        // Chaque plante a une chance de pousser : 1 sur items.grow_rate
        // (ex-constante GROW_RATE)

        $db = new Db();

        $res = $db->exe('SELECT grow_rate FROM items WHERE name = ?', $plante);

        $item = $res->fetch_object();

        if(!$item || empty($item->grow_rate)){

            // Plante sans barème : elle ne pousse pas — avant,
            // $growTo indéfini faisait un rand(1, null) imprévisible.
            return;
        }

        $growTo = (int) $item->grow_rate;

        //chance de 1/growTo
        if(AUTO_GROW || self::roll($growTo) == 1)
        {

            $values = array(
                'name'=>$plante,
                'coords_id'=>$coords
            );

            $db->insert('map_plants', $values);
            
        }
    }

}