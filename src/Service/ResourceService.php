<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
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
        $coords = $player->getCoords();
        /* `race_harvest` first, the plan JSON as a fallback — one source, so
           the two queries cannot disagree about what a plan yields. */
        $biomes = (new \App\Service\Map\HarvestCatalogService())->yieldsFor((string) $coords->plan);

        $coordsArround = null;
        $coordsIdArround=array();
        
        View::get_coords_id_arround($coordsArround,$coordsIdArround,$coords, p:1);

        $sql = '
        SELECT COUNT(*) AS max, name FROM (
            ' . self::harvestableSql($coordsIdArround, array_keys($biomes)) . '
        ) AS around
        GROUP BY name
        ';

        $db = new Db();
        $res = $db->exe($sql);

        return $res;
    }

    /**
     * Les voisines récoltables : des ENTITÉS, debout.
     *
     * Une ressource n'est plus une ligne de carte. Debout se lit sur son
     * satellite — pas de ligne, ou une ligne sans date — comme `damages = -1`
     * le disait avant.
     *
     * @param list<int> $coordsIds
     * @param list<string> $names
     */
    private static function harvestableSql(array $coordsIds, array $names): string
    {
        if ($coordsIds === [] || $names === []) {
            /* Un IN() sans terme est une erreur de syntaxe : une requête vide
               plutôt qu'une requête cassée. */
            return 'SELECT NULL AS id, NULL AS name FROM DUAL WHERE 1 = 0';
        }

        $in = implode(',', array_map('intval', $coordsIds));
        $quoted = '"' . implode('","', array_map(
            static fn(string $name): string => str_replace(['\\', '"'], ['\\\\', '\\"'], $name),
            $names
        )) . '"';

        return '
            SELECT p.id, p.race AS name
              FROM entity_cells ec
              JOIN players p ON p.id = ec.player_id AND p.player_type = "resource"
              LEFT JOIN resources r ON r.player_id = p.id
             WHERE ec.coords_id IN(' . $in . ')
               AND p.race IN (' . $quoted . ')
               AND r.exhausted_at IS NULL
        ';
    }

    public static function getResourcesAround(Player $player): mixed
    {
        $coords = $player->getCoords();
        /* `race_harvest` first, the plan JSON as a fallback — one source, so
           the two queries cannot disagree about what a plan yields. */
        $biomes = (new \App\Service\Map\HarvestCatalogService())->yieldsFor((string) $coords->plan);

        $coordsArround = null;
        $coordsIdArround=array();
        
        View::get_coords_id_arround($coordsArround,$coordsIdArround,$coords, p:1);


        $sql = '
        SELECT id, name FROM (
            ' . self::harvestableSql($coordsIdArround, array_keys($biomes)) . '
        ) AS around
        ORDER BY id
        ';

        $db = new Db();
        $res = $db->exe($sql);

        return $res;
    }

    /** @param list<int> $resourcesId identifiants d'entités */
    public static function exhaustResources(array $resourcesId): void
    {
        if ($resourcesId === []) {
            return;
        }

        (new \App\Service\Map\ResourceStateService())->exhaust(array_map('intval', $resourcesId));
    }

    public static function regrowResources(array &$resourcesId): void
    {
        if(empty($resourcesId)) {
            return;
        }

        (new \App\Service\Map\ResourceStateService())->regrow(array_map('intval', $resourcesId));
    }

    /**
     * @param array<string, array{item: string, exhaust: ?int, regrow: ?int}> $yields
     */
    public static function createExhaustArray(array $yields, array &$resourcesIdArray, &$row): void
    {
        foreach($yields as $wall => $e){
                if($wall == $row->name){
                    /* Comportement GELÉ tel quel, y compris ses deux
                     * bizarreries : l'échelle est le CENT (contre le mille
                     * pour la repousse — voulu, la repousse doit être lente),
                     * et « exhaust > 1d100 » vaut exhaust-1 chances sur cent.
                     * Une entrée de biome SANS exhaust ne s'épuise jamais :
                     * null > n est toujours faux. */
                    /* « ?? 0 » ne change RIEN au résultat — 0 comme null perd
                     * face à un dé qui vaut au moins 1 — mais supprime le
                     * warning « Undefined property » que les 41 entrées de
                     * biome sans taux déclenchaient à chaque tentative, en
                     * jeu comme au cron. */
                    if((($e['exhaust'] ?? 0) ?: 0) > self::roll(100))
                        $resourcesIdArray[] = $row->id;
                    break;
                }
            }
    }

    /**
     * Which nearby resources run out after a harvest, within a budget.
     *
     * Extracted from ResourceOutcomeInstruction so the budget rule can be
     * tested at all: it lived inside a loop that needed a player, a plan and
     * a database.
     *
     * No more veins run out than units were harvested — counting what runs
     * OUT, not what was tried. A resource whose biome declares no rate can
     * never run out, so trying it must not spend the budget.
     *
     * @param iterable<object> $rows
     * @return list<int>
     */
    public static function pickExhausted(array $yields, iterable $rows, int $budget): array
    {
        $resourcesIdArray = [];

        // Harvested nothing, exhaust nothing: the bound is read BEFORE trying.
        if ($budget < 1) {
            return $resourcesIdArray;
        }

        foreach ($rows as $row) {
            self::createExhaustArray($yields, $resourcesIdArray, $row);

            if (count($resourcesIdArray) >= $budget) {
                break;
            }
        }

        return $resourcesIdArray;
    }

    /**
     * @param array<string, array{item: string, exhaust: ?int, regrow: ?int}> $yields
     */
    public static function createRegrowArray(array $yields, array &$resourcesIdArray, &$row): void
    {
        foreach ($yields as $wall => $e) {
            if ($wall == $row->name) {
                /* Échelle du MILLE, délibérément : regrow=20 vaut donc 1,9 %
                 * par passage du cron, pas 20 %. Voir createExhaustArray. */
                if ((($e['regrow'] ?? 0) ?: 0) > self::roll(1000))
                    $resourcesIdArray[] = $row->id;
                break;
            }
        }
    }
}