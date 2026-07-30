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
        $coords = $player->getCoords();
        /* `race_harvest` first, the plan JSON as a fallback — one source, so
           the two queries cannot disagree about what a plan yields. */
        $biomes = (new \App\Service\Map\HarvestCatalogService())->yieldsFor((string) $coords->plan);

        $coordsArround = null;
        $coordsIdArround=array();
        
        View::get_coords_id_arround($coordsArround,$coordsIdArround,$coords, p:1);

        $sql = '
        SELECT SUM(n) AS max, name FROM (
            ' . self::harvestableSql($coordsIdArround, array_keys($biomes)) . '
        ) AS around
        GROUP BY name
        ';

        $db = new Db();
        $res = $db->exe($sql);

        return $res;
    }

    /**
     * Les voisines récoltables, ligne héritée OU entité.
     *
     * Les deux sources coexistent le temps de la conversion : une ressource
     * est encore une ligne `map_resources` (`damages` = -1) ou déjà une entité
     * `resource` sans état d'épuisement. La requête ne préjuge pas de laquelle,
     * et `src` dit d'où vient chaque ligne — jamais la plage d'identifiants,
     * qui a déjà menti une fois dans ce dépôt.
     *
     * @param list<int> $coordsIds
     * @param list<string> $names
     */
    private static function harvestableSql(array $coordsIds, array $names): string
    {
        if ($coordsIds === [] || $names === []) {
            /* Aucune case ou aucun rendement : une union vide, pas une requête
               invalide (un IN() sans terme est une erreur de syntaxe). */
            return 'SELECT NULL AS id, NULL AS name, NULL AS src, 0 AS n FROM DUAL WHERE 1 = 0';
        }

        $in = implode(',', array_map('intval', $coordsIds));
        $quoted = '"' . implode('","', array_map(
            static fn(string $name): string => str_replace(['\\', '"'], ['\\\\', '\\"'], $name),
            $names
        )) . '"';

        return '
            SELECT m.id, m.name, "m" AS src, 1 AS n
              FROM map_resources m
             WHERE m.coords_id IN(' . $in . ')
               AND m.name IN (' . $quoted . ')
               AND m.damages = -1

            UNION ALL

            SELECT p.id, p.race AS name, "e" AS src, 1 AS n
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
        SELECT id, name, src FROM (
            ' . self::harvestableSql($coordsIdArround, array_keys($biomes)) . '
        ) AS around
        ORDER BY src, id
        ';

        $db = new Db();
        $res = $db->exe($sql);

        return $res;
    }

    /**
     * Ce qu'on épuise ou fait repousser, désigné SANS ambiguïté.
     *
     * Une ligne héritée et une entité ont des identifiants de deux espaces
     * différents. Les distinguer par leur plage serait rejouer « un id négatif
     * est un PNJ », qui a déjà menti ici : la source est donc écrite.
     */
    private static function handle(object $row): string
    {
        return ($row->src ?? 'm') . ':' . (int) $row->id;
    }

    /**
     * @param list<string> $handles
     * @return array{m: list<int>, e: list<int>}
     */
    private static function bySource(array $handles): array
    {
        $split = ['m' => [], 'e' => []];

        foreach ($handles as $handle) {
            [$src, $id] = array_pad(explode(':', (string) $handle, 2), 2, '');

            if ($id === '' || !isset($split[$src])) {
                continue;
            }

            $split[$src][] = (int) $id;
        }

        return $split;
    }

    public static function exhaustResources(array $resourcesId): void
    {
        ['m' => $rows, 'e' => $entities] = self::bySource($resourcesId);

        if ($rows !== []) {
            (new Db())->exe(
                'UPDATE map_resources SET damages = -2 WHERE id IN(' . implode(',', $rows) . ')'
            );
        }

        if ($entities !== []) {
            (new \App\Service\Map\ResourceStateService())->exhaust($entities);
        }
    }

    public static function regrowResources(array &$resourcesId): void
    {
        if(empty($resourcesId)) {
            return;
        }

        ['m' => $rows, 'e' => $entities] = self::bySource($resourcesId);

        if ($entities !== []) {
            (new \App\Service\Map\ResourceStateService())->regrow($entities);
        }

        if ($rows === []) {
            return;
        }

        $sql = '
        UPDATE map_resources
        SET damages=-1
        WHERE
        id IN('. implode(',', $rows) .')
        ';

        $db = new Db();
        $res = $db->exe($sql);
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
                        $resourcesIdArray[] = self::handle($row);
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
                    $resourcesIdArray[] = self::handle($row);
                break;
            }
        }
    }
}