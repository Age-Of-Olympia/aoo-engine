<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
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
        /* Les plantes sont des entités : la case est libre quand aucune entité
           de type `plant` ne s'y trouve. Chercher encore dans map_plants
           reviendrait à trouver toutes les cases libres — et à semer partout. */
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
        WHERE
        t.name = 'grow'
        AND " . self::freeSoilSql('t.coords_id') . ";
        ";

        $db = new Db();

        $res = $db->exe($sql);

        return $res;
    }


    /**
     * SQL condition: nothing grows on the cell yet — no plant, no element,
     * no road. Shared by the grow triggers and the seeds left on the ground.
     */
    public static function freeSoilSql(string $coordsColumn): string
    {
        return "NOT EXISTS (SELECT 1 FROM players p
                             WHERE p.coords_id = {$coordsColumn} AND p.player_type = 'plant')
            AND NOT EXISTS (SELECT 1 FROM map_elements e WHERE e.coords_id = {$coordsColumn})
            AND NOT EXISTS (SELECT 1 FROM entity_cells rc
                              JOIN players r ON r.id = rc.player_id AND r.player_type = 'route'
                             WHERE rc.coords_id = {$coordsColumn})";
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

            self::plantAt($plante, (int) $coords);
            \App\Service\Map\BoardChanges::cellId((int) $coords);
        }
    }

    /**
     * Grow what a seed names. The type decides — a cocotier is a tree whatever
     * the seed's `table` says; `table` only serves types the catalogue lacks.
     */
    public static function sprout(string $type, string $table, int $coordsId): void
    {
        $kind = (new Db())->exe('SELECT type_kind FROM races WHERE name = ?', $type)->fetch_object()->type_kind
            ?? ['plants' => 'plant', 'resources' => 'resource'][$table]
            ?? null;

        match ($kind) {
            'plant' => self::plantAt($type, $coordsId),
            'resource' => (new \App\Service\Map\ResourceObjectService())->placeAt($type, $coordsId, false),
            default => (new Db())->insert('map_' . $table, ['name' => $type, 'coords_id' => $coordsId]),
        };
    }

    /**
     * Pose a plant entity: its row, an id in the plants range, and its
     * cell — `part`, the type says it is walked on.
     *
     * @return int the new plant's players.id
     */
    public static function plantAt(string $race, int $coordsId): int
    {
        $label = (string) ((new Db())->exe('SELECT label FROM races WHERE name = ?', $race)
            ->fetch_object()->label ?? '');

        return (new \App\Service\Map\EntityPlacementService())->create(
            'plant',
            $race,
            $coordsId,
            $label !== '' ? $label : $race,
            'img/plants/' . $race . '.png'
        );
    }

    /**
     * Plants that grew on a road go back to the ground as the seed they came
     * from, for players to pick up. A plant no seed grows into is just removed.
     *
     * @return array{plants: int, seeded: int, removed: int}
     */
    public static function seedBackFromRoads(bool $apply): array
    {
        $conn = EntityManagerFactory::getEntityManager()->getConnection();

        $plants = $conn->fetchAllAssociative(
            "SELECT p.id, p.race, p.coords_id FROM players p
              WHERE p.player_type = 'plant'
                AND EXISTS (SELECT 1 FROM entity_cells rc
                              JOIN players r ON r.id = rc.player_id AND r.player_type = 'route'
                             WHERE rc.coords_id = p.coords_id)"
        );

        $seedOf = self::seedsByPlant();
        $seeded = count(array_filter($plants, fn (array $p): bool => isset($seedOf[$p['race']])));

        if ($apply) {
            foreach ($plants as $plant) {
                if (isset($seedOf[$plant['race']])) {
                    $conn->executeStatement(
                        'INSERT INTO map_items (item_id, coords_id, n) VALUES (?, ?, 1)
                         ON DUPLICATE KEY UPDATE n = n + 1',
                        [$seedOf[$plant['race']], $plant['coords_id']]
                    );
                }
            }

            (new \App\Service\Map\ResourceObjectService($conn))
                ->removeEntities(array_map(fn (array $p): int => (int) $p['id'], $plants));
            \App\Service\Map\BoardChanges::cellId(...array_map(fn (array $p): int => (int) $p['coords_id'], $plants));
        }

        return ['plants' => count($plants), 'seeded' => $seeded, 'removed' => count($plants) - $seeded];
    }

    /**
     * Plant type => id of a seed that grows into it. Item data may still live
     * in datas/ JSON, hence get_data() rather than a SQL read of `extra`.
     *
     * @return array<string, int>
     */
    private static function seedsByPlant(): array
    {
        $seeds = [];

        foreach ((new Db())->exe('SELECT id FROM items ORDER BY id')->fetch_all() as [$id]) {
            $item = new \Classes\Item((int) $id);
            $item->get_data();

            if (($item->data->type ?? '') !== 'graine') {
                continue;
            }

            // Keyed by name, whatever `table` says: the type decides (see sprout())
            foreach ($item->data->growTo ?? [] as $growTo) {
                $seeds[$growTo->name] ??= (int) $id;
            }
        }

        return $seeds;
    }
}
