<?php

namespace App\Service\Map;

use App\Factory\EntityManagerFactory;
use App\Module\Module;
use App\Module\Modules;
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
    /** Rows per INSERT statement */
    private const BATCH = 5000;

    /** Slack for float error when a crossing falls exactly on a cell centre */
    private const EPSILON = 1e-9;

    /** An area covering any cell the INT columns can hold */
    private const WHOLE_PLAN = ['xMin' => -2147483648, 'xMax' => 2147483647, 'yMin' => -2147483648, 'yMax' => 2147483647];

    private Connection $db;

    public function __construct(?Connection $db = null)
    {
        $this->db = $db ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * Recompute the plan's biome cells inside $area (the whole plan when
     * null) and return how many rows that area now holds. Saving a biome
     * can only change the cells under its old and new outline: callers
     * pass areaOf(new outline, old outline).
     *
     * @param array{xMin: int, xMax: int, yMin: int, yMax: int}|null $area cells included
     */
    public function rebuild(int $planId, ?array $area = null): int
    {
        $area ??= self::WHOLE_PLAN;
        [$top, $hidden] = $this->stack($planId, $area);
        $transitions = $hidden === [] ? [] : $this->transitionCells($planId, $area, $hidden);

        // One row per cell for its top biome; a transition cell gets one per biome covering it.
        // Rows go out x then y, the primary key's order, so the inserts append to the index
        $rows = [];
        ksort($top);
        foreach ($top as $x => $column) {
            ksort($column);
            foreach ($column as $y => $topId) {
                $biomeIds = isset($transitions[$x][$y]) ? [...$hidden[$x][$y], $topId] : [$topId];
                foreach ($biomeIds as $biomeId) {
                    // All ints built here, so inlined instead of thousands of bound parameters
                    $rows[] = '(' . $planId . ',' . $x . ',' . $y . ',' . $biomeId . ')';
                }
            }
        }

        $this->db->transactional(function (Connection $db) use ($planId, $area, $rows): void {
            $db->executeStatement(
                'DELETE FROM biome_cells WHERE plan_id = ? AND x BETWEEN ? AND ? AND y BETWEEN ? AND ?',
                [$planId, ...self::bounds($area)]
            );
            foreach (array_chunk($rows, self::BATCH) as $chunk) {
                $db->executeStatement('INSERT INTO biome_cells (plan_id, x, y, biome_id) VALUES ' . implode(',', $chunk));
            }
        });

        return count($rows);
    }

    /**
     * Names of the biomes of a cell, the top one first; none while the
     * module is off.
     *
     * @return list<string>
     */
    public function namesAt(string $plan, int $x, int $y): array
    {
        $coverage = $this->coverage($plan, ['xMin' => $x, 'xMax' => $x, 'yMin' => $y, 'yMax' => $y]);

        return array_map(
            static fn (int $id): string => $coverage['biomes'][$id]['name'],
            $coverage['cells'][$x . ',' . $y] ?? []
        );
    }

    /**
     * The biomes over an area of the plan, for an overlay: each biome once
     * with its type's colour, and each cell's biome ids, the top one first.
     * Empty while the module is off.
     *
     * @param array{xMin: int, xMax: int, yMin: int, yMax: int} $area
     * @return array{biomes: array<int, array{name: string, color: string}>, cells: array<string, list<int>>} cells keyed "x,y"
     */
    public function coverage(string $plan, array $area): array
    {
        $coverage = ['biomes' => [], 'cells' => []];
        if (!Modules::on(Module::Biomes)) {
            return $coverage;
        }

        $rows = $this->db->fetchAllAssociative(
            'SELECT c.x, c.y, b.id, b.name, t.color FROM biome_cells c
             JOIN plans p ON p.id = c.plan_id
             JOIN biomes b ON b.id = c.biome_id
             JOIN biome_types t ON t.id = b.biome_type_id
             WHERE p.slug = ? AND c.x BETWEEN ? AND ? AND c.y BETWEEN ? AND ?
             ORDER BY b.stack_order DESC, b.id DESC',
            [$plan, ...self::bounds($area)]
        );
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $coverage['biomes'][$id] ??= ['name' => (string) $row['name'], 'color' => (string) $row['color']];
            $coverage['cells'][$row['x'] . ',' . $row['y']][] = $id;
        }

        return $coverage;
    }

    /**
     * Cells whose centre is inside the outline or on its edge, sorted.
     *
     * @param array<mixed> $zone [[x, y], ...] corner vertices
     * @return list<array{int, int}>
     */
    public static function cellsInside(array $zone): array
    {
        $cells = [];
        foreach (self::runs($zone, self::WHOLE_PLAN) as [$y, $from, $to]) {
            for ($x = $from; $x <= $to; $x++) {
                $cells[] = [$x, $y];
            }
        }
        sort($cells);

        return $cells;
    }

    /**
     * Whether the outline covers at least one cell; stops at the first.
     *
     * @param array<mixed> $zone
     */
    public static function coversACell(array $zone): bool
    {
        return self::runs($zone, self::WHOLE_PLAN)->valid();
    }

    /**
     * The rectangle of cells the outlines can cover, null when none can.
     *
     * @param array<mixed> ...$zones
     * @return array{xMin: int, xMax: int, yMin: int, yMax: int}|null
     */
    public static function areaOf(array ...$zones): ?array
    {
        $corners = array_merge(...array_map(self::corners(...), $zones));
        if ($corners === []) {
            return null;
        }
        $xs = array_column($corners, 0);
        $ys = array_column($corners, 1);

        // Corners x..x' bound the cells x..x' - 1
        return [
            'xMin' => (int) floor(min($xs)),
            'xMax' => (int) ceil(max($xs)) - 1,
            'yMin' => (int) floor(min($ys)),
            'yMax' => (int) ceil(max($ys)) - 1,
        ];
    }

    /**
     * Which biome is on top of each cell of the area, and which ones it
     * hides. Two maps rather than one list per cell: overlaps are rare, and
     * an array per cell triples the memory.
     *
     * @param array{xMin: int, xMax: int, yMin: int, yMax: int} $area
     * @return array{array<int, array<int, int>>, array<int, array<int, list<int>>>} [top, hidden], both [x][y]
     */
    private function stack(int $planId, array $area): array
    {
        $top = [];
        $hidden = [];
        $biomes = $this->db->fetchAllAssociative(
            'SELECT id, zone FROM biomes WHERE plan_id = ? ORDER BY stack_order, id',
            [$planId]
        );
        // Bottom first: each biome covers what was laid before it
        foreach ($biomes as $biome) {
            $id = (int) $biome['id'];
            foreach (self::runs((array) json_decode((string) $biome['zone'], true), $area) as [$y, $from, $to]) {
                for ($x = $from; $x <= $to; $x++) {
                    if (isset($top[$x][$y])) {
                        $hidden[$x][$y][] = $top[$x][$y];
                    }
                    $top[$x][$y] = $id;
                }
            }
        }

        return [$top, $hidden];
    }

    /**
     * The outline's cells, row by row, as runs [y, first x, last x], cut
     * to the area.
     *
     * Each row's centre line (y + .5) crosses the outline an even number of
     * times; sorted, the crossings pair up: inside from the 1st to the 2nd,
     * from the 3rd to the 4th, and so on. Corners sit on whole numbers, so
     * a centre line never runs through a corner nor along an edge.
     *
     * @param array<mixed> $zone
     * @param array{xMin: int, xMax: int, yMin: int, yMax: int} $area
     * @return \Generator<array{int, int, int}>
     */
    private static function runs(array $zone, array $area): \Generator
    {
        $corners = self::corners($zone);
        if ($corners === []) {
            return;
        }
        $ys = array_column($corners, 1);
        $firstRow = max((int) floor(min($ys)), $area['yMin']);
        $lastRow = min((int) ceil(max($ys)) - 1, $area['yMax']);

        for ($y = $firstRow; $y <= $lastRow; $y++) {
            $lastTaken = PHP_INT_MIN;
            foreach (array_chunk(self::crossings($corners, $y + 0.5), 2) as [$enter, $leave]) {
                // The cells whose centre x + .5 lies between the two crossings, both included
                $from = (int) ceil($enter - 0.5 - self::EPSILON);
                $to = (int) floor($leave - 0.5 + self::EPSILON);
                // A self-crossing outline can start a run on the cell the previous one ended on
                $from = max($from, $lastTaken + 1, $area['xMin']);
                $to = min($to, $area['xMax']);
                if ($from <= $to) {
                    yield [$y, $from, $to];
                    $lastTaken = $to;
                }
            }
        }
    }

    /**
     * Where the horizontal line at $y crosses the outline's edges, sorted.
     *
     * @param list<array{float, float}> $corners
     * @return list<float> x of each crossing
     */
    private static function crossings(array $corners, float $y): array
    {
        $xs = [];
        $from = end($corners);
        foreach ($corners as $to) {
            [$x1, $y1] = $from;
            [$x2, $y2] = $to;
            if (($y1 > $y) !== ($y2 > $y)) {
                $xs[] = $x1 + ($y - $y1) * ($x2 - $x1) / ($y2 - $y1);
            }
            $from = $to;
        }
        sort($xs);

        return $xs;
    }

    /**
     * @param array<mixed> $zone
     * @return list<array{float, float}> the well-formed corners; none when fewer than three
     */
    private static function corners(array $zone): array
    {
        $points = array_values(array_filter(
            $zone,
            static fn ($p): bool => is_array($p) && count($p) >= 2 && is_numeric($p[0]) && is_numeric($p[1])
        ));
        if (count($points) < 3) {
            return [];
        }

        return array_map(static fn (array $p): array => [(float) $p[0], (float) $p[1]], $points);
    }

    /**
     * @param array{xMin: int, xMax: int, yMin: int, yMax: int} $area
     * @return list<int> for "x BETWEEN ? AND ? AND y BETWEEN ? AND ?"
     */
    private static function bounds(array $area): array
    {
        return [$area['xMin'], $area['xMax'], $area['yMin'], $area['yMax']];
    }

    /**
     * Cells of the area under several biomes whose surface tile is a
     * transition (a wangId in terrain_tiles).
     *
     * @param array{xMin: int, xMax: int, yMin: int, yMax: int} $area
     * @param array<int, array<int, mixed>> $overlaps [x][y]
     * @return array<int, array<int, true>> [x][y]
     */
    private function transitionCells(int $planId, array $area, array $overlaps): array
    {
        $transitionTiles = array_flip($this->db->fetchFirstColumn('SELECT name FROM terrain_tiles WHERE wang_id IS NOT NULL'));
        if ($transitionTiles === []) {
            return [];
        }

        // Names compared in PHP: map_tiles and terrain_tiles differ in collation
        $tiles = $this->db->fetchAllAssociative(
            'SELECT c.x, c.y, t.name FROM map_tiles t
             JOIN coords c ON c.id = t.coords_id
             WHERE c.plan = (SELECT slug FROM plans WHERE id = ?) AND c.z = 0 AND t.foreground = 0
               AND c.x BETWEEN ? AND ? AND c.y BETWEEN ? AND ?',
            [$planId, ...self::bounds($area)]
        );
        $found = [];
        foreach ($tiles as $tile) {
            $x = (int) $tile['x'];
            $y = (int) $tile['y'];
            if (isset($overlaps[$x][$y], $transitionTiles[$tile['name']])) {
                $found[$x][$y] = true;
            }
        }

        return $found;
    }
}
