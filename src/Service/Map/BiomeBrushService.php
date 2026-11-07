<?php

namespace App\Service\Map;

use App\Factory\EntityManagerFactory;
use App\Service\TerrainTransitionService;
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
    /** Noise samples per side of the square that sets the weights' thresholds */
    private const SAMPLES = 64;

    /** Patches per side of that square: enough for the proportions to hold */
    private const SAMPLED_PATCHES = 16;

    /** @var array<string, list<float>> thresholds per (seed, scale, weights), for the whole request */
    private static array $thresholds = [];

    private Connection $db;

    /** @var array{positions: array<string, int>, plain: array<string, list<string>>, wang: array<string, string>}|null */
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
     * @return array{tiles: array<string, string>, missing: list<string>} tiles keyed "x,y"
     */
    public function paint(int $biomeId, array $area): array
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
     * Paint one cell with its top biome's palette: its ground tile is
     * replaced, the cells of the biomes recomputed (a transition can make
     * the cell belong to several).
     *
     * @return array{biome: ?string, tile: ?string, missing: list<string>} biome null: the cell has none
     */
    public function paintCell(int $coordsId): array
    {
        $cell = $this->db->fetchAssociative(
            'SELECT b.id, b.name, c.x, c.y FROM coords c
             JOIN plans p ON p.slug = c.plan
             JOIN biome_cells bc ON bc.plan_id = p.id AND bc.x = c.x AND bc.y = c.y
             JOIN biomes b ON b.id = bc.biome_id
             WHERE c.id = ?
             ORDER BY b.stack_order DESC, b.id DESC LIMIT 1',
            [$coordsId]
        );
        if ($cell === false) {
            return ['biome' => null, 'tile' => null, 'missing' => []];
        }

        [$x, $y] = [(int) $cell['x'], (int) $cell['y']];
        $painted = $this->paint((int) $cell['id'], ['xMin' => $x, 'xMax' => $x, 'yMin' => $y, 'yMax' => $y]);
        $tile = $painted['tiles'][$x . ',' . $y] ?? null;
        if ($tile !== null) {
            $this->db->transactional(static function (Connection $db) use ($coordsId, $tile): void {
                $db->delete('map_tiles', ['coords_id' => $coordsId, 'foreground' => 0]);
                $db->insert('map_tiles', ['name' => $tile, 'coords_id' => $coordsId, 'foreground' => 0]);
            });
            (new BiomeCellService($this->db))->groundChanged($coordsId);
        }

        return ['biome' => (string) $cell['name'], 'tile' => $tile, 'missing' => $painted['missing']];
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
     * @return array{positions: array<string, int>, plain: array<string, list<string>>, wang: array<string, string>}
     */
    private function loadTiles(): array
    {
        $layer = TerrainTransitionService::GROUND_LAYER;
        $positions = array_map('intval', $this->db->fetchAllKeyValue('SELECT name, position FROM terrain_colors WHERE layer = ?', [$layer]));
        $plain = [];
        $wang = [];
        foreach ($this->db->fetchAllAssociative(
            'SELECT name, color, wang_id FROM terrain_tiles WHERE layer = ? ORDER BY name',
            [$layer]
        ) as $row) {
            if ($row['wang_id'] === null) {
                $plain[(string) $row['color']][] = (string) $row['name'];
                continue;
            }
            $wangId = array_map('intval', explode(',', (string) $row['wang_id']));
            $corners = array_map(static fn (int $slot): int => $wangId[$slot], TerrainTransitionService::WANG_POSITIONS);
            $wang[implode(',', $corners)] ??= (string) $row['name'];
        }

        return ['positions' => $positions, 'plain' => $plain, 'wang' => $wang];
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
