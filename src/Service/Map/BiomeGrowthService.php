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
 * of 1 in `chance`, until the biome holds `max_count` of that type, players'
 * plantings included. A free cell has a ground tile, no plant, element,
 * road or other entity on it.
 */
final class BiomeGrowthService
{
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
            'SELECT bp.biome_id, bp.chance, bp.max_count, bp.z_min, bp.z_max, r.name AS race
             FROM biome_populations bp JOIN races r ON r.id = bp.race_id'
        );
        foreach ($populations as $population) {
            $room = (int) $population['max_count'] - $this->count($population);
            if ($room <= 0) {
                continue;
            }

            $cells = $this->freeCells($population);
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
     * @param array<string, mixed> $population
     * @return list<int> coords ids
     */
    private function freeCells(array $population): array
    {
        return array_map('intval', $this->db->fetchFirstColumn(
            'SELECT c.id FROM biome_cells bc
             JOIN plans pl ON pl.id = bc.plan_id
             JOIN coords c ON c.plan = pl.slug AND c.x = bc.x AND c.y = bc.y
             WHERE bc.biome_id = ? AND c.z BETWEEN ? AND ?
               AND EXISTS (SELECT 1 FROM map_tiles t WHERE t.coords_id = c.id AND t.foreground = 0)
               AND NOT EXISTS (SELECT 1 FROM entity_cells ec WHERE ec.coords_id = c.id)
               AND ' . PlantsService::freeSoilSql('c.id'),
            [$population['biome_id'], $population['z_min'], $population['z_max']]
        ));
    }
}
