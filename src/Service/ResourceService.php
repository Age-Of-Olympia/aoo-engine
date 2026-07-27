<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use Classes\Db;
use Classes\Dice;
use Classes\Player;
use Classes\View;

class ResourceService
{
    /**
     * Dé injectable pour les tirages d'épuisement et de repousse.
     *
     * Le service est entièrement statique ; le point d'injection l'est donc
     * aussi, sur le modèle de ResourceTypeService::setCatalogForTests(). Nul
     * en jeu : un dé de la bonne taille est fabriqué à chaque tirage.
     */
    private static ?Dice $dice = null;

    public static function setDiceForTests(?Dice $dice): void
    {
        self::$dice = $dice;
    }

    /** Un dé à $sides faces, une fois. */
    private static function roll(int $sides): int
    {
        $dice = self::$dice ?? new Dice(max(1, $sides));

        return (int) $dice->roll(1)[0];
    }

    public static function findResourcesAround(Player $player): mixed
    {
        $biomes = array();
        $coords = $player->getCoords();
        $planJson = json()->decode('plans', $coords->plan);

        if (!$planJson) {
            $planJson = (object) ['biomes' => []];
        }
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
        map_resources
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

        if (!$planJson) {
            $planJson = (object) ['biomes' => []];
        }
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
        map_resources
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
        UPDATE map_resources
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
        UPDATE map_resources
        SET damages=-1
        WHERE 
        id IN('. implode(',', $resourcesId) .')
        ';

        $db = new Db();
        $res = $db->exe($sql);
    }

    public static function createExhaustArray(&$planJson, array &$resourcesIdArray, &$row): void
    {
        if (!isset($planJson->biomes)) {
            return;
        }

        foreach($planJson->biomes as $e){
                if($e->wall == $row->name){
                    /* Comportement GELÉ tel quel, y compris ses deux
                     * bizarreries : l'échelle est le CENT (contre le mille
                     * pour la repousse — voulu, la repousse doit être lente),
                     * et « exhaust > 1d100 » vaut exhaust-1 chances sur cent.
                     * Une entrée de biome SANS exhaust ne s'épuise jamais :
                     * null > n est toujours faux. */
                    if($e->exhaust > self::roll(100))
                        $resourcesIdArray[] = $row->id;
                    break;
                }
            }
    }

    public static function createRegrowArray(&$planJson, array &$resourcesIdArray, &$row): void
    {
        if(!isset($planJson->biomes)) {
            return;
        }
        foreach ($planJson->biomes as $e) {
            if ($e->wall == $row->name) {
                /* Échelle du MILLE, délibérément : regrow=20 vaut donc 1,9 %
                 * par passage du cron, pas 20 %. Voir createExhaustArray. */
                if ($e->regrow > self::roll(1000))
                    $resourcesIdArray[] = $row->id;
                break;
            }
        }
    }
}