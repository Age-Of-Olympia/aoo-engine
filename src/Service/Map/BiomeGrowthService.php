<?php

namespace App\Service\Map;

use App\Factory\EntityManagerFactory;
use App\Module\Module;
use App\Module\Modules;
use App\Service\PlantsService;
use Doctrine\DBAL\Connection;

/**
 * The nightly growth of biomes (biome_populations).
 *
 * Each free cell of the biome, on the population's levels, gets a chance
 * of 1 in `chance`, until the biome holds `density` of that type per 100
 * cells with ground, players' plantings included. A free cell has a ground
 * tile, no plant, element, road or other entity on it.
 */
final class BiomeGrowthService
{
    /** A density counts plants per this many cells with ground */
    private const DENSITY_CELLS = 100;

    private Connection $db;

    /** @var callable(int): int a die with that many sides */
    private $roll;

    public function __construct(?Connection $db = null, ?callable $roll = null)
    {
        $this->db = $db ?? EntityManagerFactory::getEntityManager()->getConnection();
        $this->roll = $roll ?? static fn (int $sides): int => random_int(1, max(1, $sides));
    }

    /** @return list<int> the coords ids where something grew */
    public function growNight(): array
    {
        if (!Modules::on(Module::Biomes)) {
            return [];
        }

        $grown = [];
        $populations = $this->db->fetchAllAssociative(
            'SELECT bp.biome_id, bp.chance, bp.density, bp.z_min, bp.z_max, r.name AS race
             FROM biome_populations bp JOIN races r ON r.id = bp.race_id'
        );
        foreach ($populations as $population) {
            $ground = $this->groundCells($population);
            $room = self::capOf($population, count($ground)) - $this->count($population);
            if ($room <= 0) {
                continue;
            }

            $cells = array_keys(array_filter($ground));
            shuffle($cells);
            foreach ($cells as $coordsId) {
                if ($room <= 0) {
                    break;
                }
                if (($this->roll)((int) $population['chance']) === 1) {
                    PlantsService::sprout((string) $population['race'], 'plants', $coordsId);
                    $grown[] = $coordsId;
                    $room--;
                }
            }
        }

        return $grown;
    }

    /**
     * How many of that type the biome holds at most, with today's ground.
     *
     * @param array<string, mixed> $population biome_id, density, z_min, z_max
     */
    public function cap(array $population): int
    {
        return self::capOf($population, count($this->groundCells($population)));
    }

    /** @param array<string, mixed> $population */
    private static function capOf(array $population, int $groundCells): int
    {
        return (int) floor((float) $population['density'] * $groundCells / self::DENSITY_CELLS);
    }

    /** @param array<string, mixed> $population */
    private function count(array $population): int
    {
        return (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM players p
             JOIN coords c ON c.id = p.coords_id
             JOIN plans pl ON pl.slug = c.plan
             JOIN biome_cells bc ON bc.plan_id = pl.id AND bc.x = c.x AND bc.y = c.y
             WHERE bc.biome_id = ? AND p.race = ? AND c.z BETWEEN ? AND ?',
            [$population['biome_id'], $population['race'], $population['z_min'], $population['z_max']]
        );
    }

    /**
     * The biome's cells with ground on the population's levels: they set the cap, the free ones get the rolls.
     *
     * @param array<string, mixed> $population
     * @return array<int, bool> coords id => free
     */
    private function groundCells(array $population): array
    {
        return array_map('boolval', $this->db->fetchAllKeyValue(
            'SELECT c.id, NOT EXISTS (SELECT 1 FROM entity_cells ec WHERE ec.coords_id = c.id)
                 AND ' . PlantsService::freeSoilSql('c.id') . '
             FROM biome_cells bc
             JOIN plans pl ON pl.id = bc.plan_id
             JOIN coords c ON c.plan = pl.slug AND c.x = bc.x AND c.y = bc.y
             WHERE bc.biome_id = ? AND c.z BETWEEN ? AND ?
               AND EXISTS (SELECT 1 FROM map_tiles t WHERE t.coords_id = c.id AND t.foreground = 0)',
            [$population['biome_id'], $population['z_min'], $population['z_max']]
        ));
    }
}
