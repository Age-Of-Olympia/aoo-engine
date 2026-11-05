<?php

namespace App\Service\Map;

use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * Sole writer of `biome_cells` — the cells of each biome, from the outlines.
 *
 * An outline is a list of [x, y] vertices on cell corners: cell (x, y)
 * spans the corners x..x+1 and y..y+1, its centre is (x + .5, y + .5). A
 * cell is in a biome when its centre is inside the outline or on its edge.
 * Where outlines overlap, the highest stack_order wins (then the highest
 * id), except on a transition tile of the surface ground: there the cell
 * belongs to every biome covering it.
 */
final class BiomeCellService
{
    private const INSERT_BATCH = 1000;

    private Connection $db;

    public function __construct(?Connection $db = null)
    {
        $this->db = $db ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** Recompute every cell of the plan's biomes; returns the rows written. */
    public function rebuild(int $planId): int
    {
        // One id per cell, the top one; the ones it hides only where zones overlap
        $top = [];
        $under = [];
        $biomes = $this->db->fetchAllAssociative(
            'SELECT id, zone FROM biomes WHERE plan_id = ? ORDER BY stack_order, id',
            [$planId]
        );
        foreach ($biomes as $biome) {
            $id = (int) $biome['id'];
            foreach (self::eachCellInside((array) json_decode((string) $biome['zone'], true)) as [$x, $y]) {
                $key = $x . ',' . $y;
                if (isset($top[$key])) {
                    $under[$key][] = $top[$key];
                }
                $top[$key] = $id;
            }
        }
        $transitions = $under === [] ? [] : $this->transitionCells($planId, array_keys($under));

        return (int) $this->db->transactional(function (Connection $db) use ($planId, $top, $under, $transitions): int {
            $db->executeStatement('DELETE FROM biome_cells WHERE plan_id = ?', [$planId]);

            $written = 0;
            $chunk = [];
            foreach ($top as $key => $id) {
                [$x, $y] = explode(',', (string) $key);
                foreach (isset($transitions[$key]) ? [...$under[$key], $id] : [$id] as $biomeId) {
                    $chunk[] = [$planId, (int) $x, (int) $y, $biomeId];
                }
                if (count($chunk) >= self::INSERT_BATCH) {
                    $written += self::insert($db, $chunk);
                    $chunk = [];
                }
            }
            $written += self::insert($db, $chunk);

            return $written;
        });
    }

    /** @param list<array{int, int, int, int}> $rows */
    private static function insert(Connection $db, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        return $db->executeStatement(
            'INSERT INTO biome_cells (plan_id, x, y, biome_id) VALUES '
                . implode(',', array_fill(0, count($rows), '(?, ?, ?, ?)')),
            array_merge(...$rows)
        );
    }

    /**
     * Cells whose centre is inside the outline or on its edge.
     *
     * @param array<mixed> $zone [[x, y], ...] corner vertices
     * @return list<array{int, int}>
     */
    public static function cellsInside(array $zone): array
    {
        return iterator_to_array(self::eachCellInside($zone), false);
    }

    /**
     * @param array<mixed> $zone
     * @return \Generator<array{int, int}>
     */
    private static function eachCellInside(array $zone): \Generator
    {
        $points = array_values(array_filter(
            $zone,
            static fn ($p): bool => is_array($p) && count($p) >= 2 && is_numeric($p[0]) && is_numeric($p[1])
        ));
        if (count($points) < 3) {
            return;
        }
        $points = array_map(static fn (array $p): array => [(float) $p[0], (float) $p[1]], $points);

        $xs = array_column($points, 0);
        $ys = array_column($points, 1);
        for ($x = (int) floor(min($xs)); $x < (int) ceil(max($xs)); $x++) {
            for ($y = (int) floor(min($ys)); $y < (int) ceil(max($ys)); $y++) {
                if (self::contains($points, $x + 0.5, $y + 0.5)) {
                    yield [$x, $y];
                }
            }
        }
    }

    /**
     * Even-odd ray cast; a point on an edge counts as inside, so a cell
     * cut through its centre by a diagonal belongs to both zones.
     *
     * @param list<array{float, float}> $points
     */
    private static function contains(array $points, float $px, float $py): bool
    {
        $inside = false;
        $n = count($points);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $points[$i];
            [$xj, $yj] = $points[$j];

            $cross = ($xj - $xi) * ($py - $yi) - ($yj - $yi) * ($px - $xi);
            if (abs($cross) < 1e-9
                && $px >= min($xi, $xj) && $px <= max($xi, $xj)
                && $py >= min($yi, $yj) && $py <= max($yi, $yj)) {
                return true;
            }

            if (($yi > $py) !== ($yj > $py)
                && $px < ($xj - $xi) * ($py - $yi) / ($yj - $yi) + $xi) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    /**
     * Overlapping cells whose surface tile is a transition (a wangId in
     * terrain_tiles).
     *
     * @param list<string> $keys "x,y"
     * @return array<string, true>
     */
    private function transitionCells(int $planId, array $keys): array
    {
        $wang = array_flip($this->db->fetchFirstColumn('SELECT name FROM terrain_tiles WHERE wang_id IS NOT NULL'));
        if ($wang === []) {
            return [];
        }

        // Names compared in PHP: map_tiles and terrain_tiles differ in collation
        $tiles = $this->db->fetchAllAssociative(
            'SELECT c.x, c.y, t.name FROM map_tiles t
             JOIN coords c ON c.id = t.coords_id
             WHERE c.plan = (SELECT slug FROM plans WHERE id = ?) AND c.z = 0 AND t.foreground = 0',
            [$planId]
        );
        $wanted = array_flip($keys);
        $found = [];
        foreach ($tiles as $tile) {
            $key = $tile['x'] . ',' . $tile['y'];
            if (isset($wanted[$key], $wang[$tile['name']])) {
                $found[$key] = true;
            }
        }

        return $found;
    }
}
