<?php

namespace App\Service\Map;

use App\Factory\EntityManagerFactory;
use App\Service\TerrainTransitionService;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;

/**
 * The biome brush: the ground tiles a biome's palette gives its cells.
 *
 * The noise picks a terrain at each CORNER of the cells, then each cell
 * takes the tile whose four corners match: a plain tile when they agree,
 * the wang transition otherwise. Two neighbours share their corners, so the
 * transitions always join up. The result depends only on (x, y, the
 * biome's seed and settings): painting in several strokes draws what one
 * stroke over the whole area would.
 */
final class BiomeBrushService
{
    /** Rows per INSERT or DELETE statement */
    private const BATCH = 1000;

    /** Noise samples per side of the square that sets the weights' thresholds */
    private const SAMPLES = 64;

    /** Patches per side of that square: enough for the proportions to hold */
    private const SAMPLED_PATCHES = 16;

    /** @var array<string, list<float>> thresholds per (seed, scale, weights), for the whole request */
    private static array $thresholds = [];

    private Connection $db;

    /** @var array{positions: array<string, int>, plain: array<string, list<string>>, wang: array<string, string>, corners: array<string, list<string>>}|null */
    private ?array $tiles = null;

    public function __construct(?Connection $db = null)
    {
        $this->db = $db ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * The tile of each cell of the area, and the corner combinations no tile
     * covers (the cell then takes the plain tile of its top-left terrain).
     *
     * @param array{xMin: int, xMax: int, yMin: int, yMax: int} $area cells included
     * @param array<string, string> $fixedCorners "x,y" corner => terrain decided beforehand (the ground across the edge)
     * @return array{tiles: array<string, string>, missing: list<string>} tiles keyed "x,y"
     */
    public function paint(int $biomeId, array $area, array $fixedCorners = []): array
    {
        $settings = $this->settings($biomeId);
        $noise = new GroundNoise($settings['seed']);
        $thresholds = $this->thresholds($noise, $settings);
        $terrains = array_keys($settings['weights']);
        $tiles = $this->tiles ??= $this->loadTiles();

        // One terrain per corner; the area's cells span the corners xMin..xMax+1, yMin..yMax+1
        $corner = [];
        for ($cx = $area['xMin']; $cx <= $area['xMax'] + 1; $cx++) {
            for ($cy = $area['yMin']; $cy <= $area['yMax'] + 1; $cy++) {
                if (isset($fixedCorners[$cx . ',' . $cy])) {
                    $corner[$cx][$cy] = $fixedCorners[$cx . ',' . $cy];
                    continue;
                }
                $value = $noise->at($cx / $settings['scale'], $cy / $settings['scale']);
                $index = 0;
                while ($index < count($thresholds) && $value >= $thresholds[$index]) {
                    $index++;
                }
                $corner[$cx][$cy] = $terrains[$index];
            }
        }

        $painted = [];
        $missing = [];
        for ($x = $area['xMin']; $x <= $area['xMax']; $x++) {
            for ($y = $area['yMin']; $y <= $area['yMax']; $y++) {
                // The game's y grows northwards: the top of cell (x, y) is its y + 1 side
                $four = [$corner[$x][$y + 1], $corner[$x + 1][$y + 1], $corner[$x + 1][$y], $corner[$x][$y]];
                $tile = count(array_unique($four)) === 1
                    ? self::variant($tiles['plain'][$four[0]] ?? [], $x, $y, $settings['seed'])
                    : $tiles['wang'][self::wangKey($four, $tiles['positions'])] ?? null;
                if ($tile === null) {
                    $missing[implode(' / ', $four)] = true;
                    $tile = self::variant($tiles['plain'][$four[0]] ?? [], $x, $y, $settings['seed']);
                }
                if ($tile !== null) {
                    $painted[$x . ',' . $y] = $tile;
                }
            }
        }

        return ['tiles' => $painted, 'missing' => array_keys($missing)];
    }

    /**
     * Paint the whole biome on top at this cell, on the cell's level: every
     * cell where that biome is on top gets its ground tile, in one batch,
     * then the biomes' cells and the boards around are refreshed once.
     *
     * At the biome's edge, the corners it shares with the ground already
     * laid next to it keep that ground's terrains: the edge cells take a
     * transition towards the neighbour, generated when the pair lacks it.
     *
     * @return array{biome: ?string, coords: list<int>, missing: list<string>, unreadable: list<string>, generated: int, errors: list<string>}
     *     biome null: the cell has none; unreadable: neighbouring tiles classified in no terrain
     */
    public function paintBiome(int $coordsId): array
    {
        $biome = $this->db->fetchAssociative(
            'SELECT b.id, b.name, b.plan_id, c.plan, c.z FROM coords c
             JOIN plans p ON p.slug = c.plan
             JOIN biome_cells bc ON bc.plan_id = p.id AND bc.x = c.x AND bc.y = c.y
             JOIN biomes b ON b.id = bc.biome_id
             WHERE c.id = ?
             ORDER BY b.stack_order DESC, b.id DESC LIMIT 1',
            [$coordsId]
        );
        if ($biome === false) {
            return ['biome' => null, 'coords' => [], 'missing' => [], 'unreadable' => [], 'generated' => 0, 'errors' => []];
        }

        $cells = $this->topCells((int) $biome['id']);
        $area = [
            'xMin' => min(array_column($cells, 0)), 'xMax' => max(array_column($cells, 0)),
            'yMin' => min(array_column($cells, 1)), 'yMax' => max(array_column($cells, 1)),
        ];
        [$plan, $z] = [(string) $biome['plan'], (int) $biome['z']];
        $edge = $this->edgeCorners($plan, $z, $cells, $area);
        $painted = $this->paint((int) $biome['id'], $area, $edge['fixed']);

        // Joins towards the neighbours can need transitions no palette asked for
        $generated = 0;
        $errors = [];
        if ($painted['missing'] !== []) {
            $transitions = new TerrainTransitionService();
            foreach ($painted['missing'] as $combination) {
                $terrains = array_values(array_unique(explode(' / ', $combination)));
                if (count($terrains) > BiomeAdminService::MAX_GROUNDS) {
                    continue;
                }
                try {
                    $generated += count($transitions->generateForTerrains($terrains));
                } catch (\RuntimeException $e) {
                    $errors[$e->getMessage()] = true;
                }
            }
            if ($generated > 0) {
                $this->tiles = null;
                $painted = $this->paint((int) $biome['id'], $area, $edge['fixed']);
            }
        }

        $coordsIds = $this->coordsIds($plan, $z, $cells);
        $rows = [];
        foreach ($coordsIds as $key => $id) {
            if (isset($painted['tiles'][$key])) {
                $rows[$id] = $painted['tiles'][$key];
            }
        }
        $this->db->transactional(static function (Connection $db) use ($rows): void {
            foreach (array_chunk($rows, self::BATCH, true) as $chunk) {
                $db->executeStatement(
                    'DELETE FROM map_tiles WHERE foreground = 0 AND coords_id IN (?)',
                    [array_keys($chunk)],
                    [ArrayParameterType::INTEGER]
                );
                $db->executeStatement(
                    'INSERT INTO map_tiles (name, coords_id, foreground) VALUES ' . implode(',', array_fill(0, count($chunk), '(?, ?, 0)')),
                    array_merge(...array_map(static fn (int $id, string $tile): array => [$tile, $id], array_keys($chunk), $chunk))
                );
            }
        });

        // Transitions just painted can make cells belong to several biomes
        (new BiomeCellService($this->db))->rebuild((int) $biome['plan_id'], $area);
        BoardChanges::cells($plan, $z, $area['xMin'], $area['xMax'], $area['yMin'], $area['yMax']);

        return [
            'biome' => (string) $biome['name'],
            'coords' => array_keys($rows),
            'missing' => $painted['missing'],
            'unreadable' => $edge['unreadable'],
            'generated' => $generated,
            'errors' => array_keys($errors),
        ];
    }

    /**
     * The terrains of the corners the biome shares with the ground laid
     * just outside it, on that level, read from the tiles there.
     *
     * @param list<array{int, int}> $cells the biome's cells
     * @param array{xMin: int, xMax: int, yMin: int, yMax: int} $area their rectangle
     * @return array{fixed: array<string, string>, unreadable: list<string>} fixed: "x,y" corner => terrain
     */
    private function edgeCorners(string $plan, int $z, array $cells, array $area): array
    {
        $inside = array_flip(array_map(static fn (array $cell): string => $cell[0] . ',' . $cell[1], $cells));
        $tiles = $this->tiles ??= $this->loadTiles();

        $grounds = [];
        foreach ($this->db->fetchAllAssociative(
            'SELECT c.x, c.y, t.name FROM coords c JOIN map_tiles t ON t.coords_id = c.id AND t.foreground = 0
             WHERE c.plan = ? AND c.z = ? AND c.x BETWEEN ? AND ? AND c.y BETWEEN ? AND ?
             ORDER BY t.id',
            [$plan, $z, $area['xMin'] - 1, $area['xMax'] + 1, $area['yMin'] - 1, $area['yMax'] + 1]
        ) as $row) {
            $key = $row['x'] . ',' . $row['y'];
            if (!isset($inside[$key])) {
                // The last row laid is the one drawn
                $grounds[$key] = [(int) $row['x'], (int) $row['y'], (string) $row['name']];
            }
        }

        $fixed = [];
        $unreadable = [];
        foreach ($grounds as [$x, $y, $name]) {
            $four = $tiles['corners'][$name] ?? null;
            if ($four === null) {
                $unreadable[$name] = true;
                continue;
            }
            // TL, TR, BR, BL of cell (x, y): the game's y grows northwards
            foreach ([[$x, $y + 1], [$x + 1, $y + 1], [$x + 1, $y], [$x, $y]] as $i => [$cx, $cy]) {
                $fixed[$cx . ',' . $cy] ??= $four[$i];
            }
        }

        return ['fixed' => $fixed, 'unreadable' => array_keys($unreadable)];
    }

    /**
     * The cells where this biome is on top (highest stack order, then highest id).
     *
     * @return list<array{int, int}> [x, y]
     */
    private function topCells(int $biomeId): array
    {
        return array_map(static fn (array $row): array => [(int) $row['x'], (int) $row['y']], $this->db->fetchAllAssociative(
            'SELECT bc.x, bc.y FROM biome_cells bc JOIN biomes b ON b.id = bc.biome_id
             WHERE bc.biome_id = ? AND NOT EXISTS (
                 SELECT 1 FROM biome_cells o JOIN biomes ob ON ob.id = o.biome_id
                 WHERE o.plan_id = bc.plan_id AND o.x = bc.x AND o.y = bc.y
                   AND (ob.stack_order > b.stack_order OR (ob.stack_order = b.stack_order AND ob.id > b.id)))',
            [$biomeId]
        ));
    }

    /**
     * The coords ids of these cells on that level, created where missing.
     *
     * @param list<array{int, int}> $cells
     * @return array<string, int> "x,y" => coords id
     */
    private function coordsIds(string $plan, int $z, array $cells): array
    {
        foreach (array_chunk($cells, self::BATCH) as $chunk) {
            $this->db->executeStatement(
                'INSERT IGNORE INTO coords (x, y, z, plan) VALUES ' . implode(',', array_fill(0, count($chunk), '(?, ?, ?, ?)')),
                array_merge(...array_map(static fn (array $cell): array => [$cell[0], $cell[1], $z, $plan], $chunk))
            );
        }
        $wanted = array_flip(array_map(static fn (array $cell): string => $cell[0] . ',' . $cell[1], $cells));
        $ids = [];
        foreach ($this->db->fetchAllAssociative(
            'SELECT id, x, y FROM coords WHERE plan = ? AND z = ? AND x BETWEEN ? AND ? AND y BETWEEN ? AND ?',
            [$plan, $z, min(array_column($cells, 0)), max(array_column($cells, 0)), min(array_column($cells, 1)), max(array_column($cells, 1))]
        ) as $row) {
            $key = $row['x'] . ',' . $row['y'];
            if (isset($wanted[$key])) {
                $ids[$key] = (int) $row['id'];
            }
        }

        return $ids;
    }

    /** A new seed for the biome: its next painting draws other patches. */
    public function reseed(int $biomeId): int
    {
        $seed = random_int(1, PHP_INT_MAX >> 32);
        $this->db->update('biomes', ['seed' => $seed], ['id' => $biomeId]);

        return $seed;
    }

    /**
     * @return array{seed: int, scale: int, weights: array<string, int>}
     */
    private function settings(int $biomeId): array
    {
        $biome = $this->db->fetchAssociative(
            'SELECT b.seed, b.biome_type_id, t.patch_scale FROM biomes b JOIN biome_types t ON t.id = b.biome_type_id WHERE b.id = ?',
            [$biomeId]
        );
        if ($biome === false) {
            throw new InvalidArgumentException('Biome inconnu.');
        }
        $weights = array_map('intval', $this->db->fetchAllKeyValue(
            'SELECT terrain, weight FROM biome_type_grounds WHERE biome_type_id = ? AND weight > 0 ORDER BY terrain',
            [$biome['biome_type_id']]
        ));
        if ($weights === []) {
            throw new InvalidArgumentException('Ce type de biome n\'a pas de sols : réglez sa palette dans Le monde > Biomes.');
        }

        return ['seed' => (int) $biome['seed'], 'scale' => max(1, (int) $biome['patch_scale']), 'weights' => $weights];
    }

    /**
     * The noise values where one terrain gives way to the next, so each
     * terrain covers its share of the corners: quantiles of a fixed sample
     * of the field, the same whatever area is painted.
     *
     * @param array{seed: int, scale: int, weights: array<string, int>} $settings
     * @return list<float> one threshold between two consecutive terrains
     */
    private function thresholds(GroundNoise $noise, array $settings): array
    {
        $key = $settings['seed'] . ':' . $settings['scale'] . ':' . json_encode($settings['weights']);
        if (isset(self::$thresholds[$key])) {
            return self::$thresholds[$key];
        }

        $step = self::SAMPLED_PATCHES * $settings['scale'] / self::SAMPLES;
        $values = [];
        for ($i = 0; $i < self::SAMPLES; $i++) {
            for ($j = 0; $j < self::SAMPLES; $j++) {
                $values[] = $noise->at($i * $step / $settings['scale'], $j * $step / $settings['scale']);
            }
        }
        sort($values);

        $total = array_sum($settings['weights']);
        $thresholds = [];
        $covered = 0;
        foreach (array_slice($settings['weights'], 0, -1) as $weight) {
            $covered += $weight;
            $thresholds[] = $values[min(count($values) - 1, (int) floor($covered / $total * count($values)))];
        }

        return self::$thresholds[$key] = $thresholds;
    }

    /**
     * The ground tiles by terrain (plain tiles, several when a terrain has
     * variants) and by corners (wang transitions).
     *
     * @return array{positions: array<string, int>, plain: array<string, list<string>>, wang: array<string, string>, corners: array<string, list<string>>}
     *     corners: the terrains at TL, TR, BR, BL of every classified tile
     */
    private function loadTiles(): array
    {
        $layer = TerrainTransitionService::GROUND_LAYER;
        $positions = array_map('intval', $this->db->fetchAllKeyValue('SELECT name, position FROM terrain_colors WHERE layer = ?', [$layer]));
        $terrainAt = array_flip($positions);
        $plain = [];
        $wang = [];
        $corners = [];
        foreach ($this->db->fetchAllAssociative(
            'SELECT name, color, wang_id FROM terrain_tiles WHERE layer = ? ORDER BY name',
            [$layer]
        ) as $row) {
            $name = (string) $row['name'];
            if ($row['wang_id'] === null) {
                $plain[(string) $row['color']][] = $name;
                $corners[$name] = array_fill(0, 4, (string) $row['color']);
                continue;
            }
            $wangId = array_map('intval', explode(',', (string) $row['wang_id']));
            $slots = array_map(static fn (int $slot): int => $wangId[$slot], TerrainTransitionService::WANG_POSITIONS);
            $wang[implode(',', $slots)] ??= $name;
            if (!in_array(0, $slots, true)) {
                $corners[$name] = array_map(static fn (int $position): string => $terrainAt[$position] ?? '', $slots);
            }
        }

        return ['positions' => $positions, 'plain' => $plain, 'wang' => $wang, 'corners' => $corners];
    }

    /**
     * @param list<string> $four terrains at TL, TR, BR, BL
     * @param array<string, int> $positions
     */
    private static function wangKey(array $four, array $positions): string
    {
        return implode(',', array_map(static fn (string $terrain): int => $positions[$terrain] ?? 0, $four));
    }

    /**
     * One of the terrain's plain tiles, always the same for that cell.
     *
     * @param list<string> $variants
     */
    private static function variant(array $variants, int $x, int $y, int $seed): ?string
    {
        return $variants === [] ? null : $variants[crc32($x . ',' . $y . ',' . $seed) % count($variants)];
    }
}
