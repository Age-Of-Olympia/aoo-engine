<?php

namespace App\Service\Map;

use App\Factory\EntityManagerFactory;
use App\Service\TerrainTransitionService;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use RuntimeException;

/**
 * A plan's biomes in a plan bundle: each biome carries its type and what
 * grows in it, by natural keys (type name, race name), so the bundle moves
 * between servers. Types belong to no plan: an import creates a missing
 * type and leaves an existing one as the target server set it.
 */
final class BiomeBundleService
{
    private Connection $db;

    public function __construct(?Connection $db = null)
    {
        $this->db = $db ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** @return list<array<string, mixed>> */
    public function export(string $plan): array
    {
        $crossing = implode(', ', array_map(
            static fn (string $column): string => "t.{$column} AS type_{$column}, b.{$column} AS biome_{$column}",
            BiomeAdminService::CROSSING_COLUMNS
        ));
        $biomes = $this->db->fetchAllAssociative(
            'SELECT b.id, b.name, b.zone, b.stack_order, b.seed, t.id AS type_id, t.name AS type, t.label, t.color, t.patch_scale, t.edge_width, '
            . $crossing . '
             FROM biomes b JOIN plans p ON p.id = b.plan_id JOIN biome_types t ON t.id = b.biome_type_id
             WHERE p.slug = ? ORDER BY b.stack_order, b.id',
            [$plan]
        );
        if ($biomes === []) {
            return [];
        }
        $biomeIds = array_column($biomes, 'id');

        $grounds = [];
        foreach ($this->db->fetchAllAssociative(
            'SELECT biome_type_id, terrain, weight FROM biome_type_grounds WHERE biome_type_id IN (?) ORDER BY terrain',
            [array_unique(array_column($biomes, 'type_id'))],
            [ArrayParameterType::INTEGER]
        ) as $row) {
            $grounds[(int) $row['biome_type_id']][(string) $row['terrain']] = (int) $row['weight'];
        }
        $populations = [];
        foreach ($this->db->fetchAllAssociative(
            'SELECT bp.biome_id, r.name AS race, bp.chance, bp.density, bp.z_min, bp.z_max
             FROM biome_populations bp JOIN races r ON r.id = bp.race_id
             WHERE bp.biome_id IN (?) ORDER BY r.name',
            [$biomeIds],
            [ArrayParameterType::INTEGER]
        ) as $row) {
            $populations[(int) $row['biome_id']][] = [
                'race' => (string) $row['race'],
                'chance' => (int) $row['chance'],
                'density' => (float) $row['density'],
                'z_min' => (int) $row['z_min'],
                'z_max' => (int) $row['z_max'],
            ];
        }

        return array_map(static fn (array $biome): array => [
            'name' => (string) $biome['name'],
            'type' => [
                'name' => (string) $biome['type'],
                'label' => (string) $biome['label'],
                'color' => (string) $biome['color'],
                'patch_scale' => (int) $biome['patch_scale'],
                'edge_width' => (int) $biome['edge_width'],
                'grounds' => $grounds[(int) $biome['type_id']] ?? [],
                'crossing' => self::crossingOf($biome, 'type_'),
            ],
            'zone' => json_decode((string) $biome['zone'], true),
            'stack_order' => (int) $biome['stack_order'],
            'seed' => (int) $biome['seed'],
            'populations' => $populations[(int) $biome['id']] ?? [],
            'crossing' => self::crossingOf($biome, 'biome_'),
        ], $biomes);
    }

    /** @param array<mixed> $owner a type or a biome of the bundle: its "crossing", if any, holds plain values */
    private static function crossingIsScalar(array $owner): bool
    {
        $crossing = $owner['crossing'] ?? [];

        return is_array($crossing) && array_filter($crossing, static fn (mixed $value): bool => $value !== null && !is_scalar($value)) === [];
    }

    /**
     * What entering and leaving does, read from the export's prefixed columns.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function crossingOf(array $row, string $prefix): array
    {
        $crossing = [];
        foreach (BiomeAdminService::CROSSING_COLUMNS as $column) {
            $crossing[$column] = $row[$prefix . $column];
        }

        return $crossing;
    }

    /**
     * The bundle's entry and exit settings, its known columns only; an older bundle has none.
     *
     * @param array<string, mixed> $owner a type or a biome of the bundle
     * @return array<string, mixed>
     */
    private static function crossingFrom(array $owner): array
    {
        return array_intersect_key((array) ($owner['crossing'] ?? []), array_flip(BiomeAdminService::CROSSING_COLUMNS));
    }

    /**
     * The bundle's "biomes" key, checked before anything is written.
     *
     * @return list<array<string, mixed>>
     */
    public static function validate(mixed $biomes): array
    {
        if (!is_array($biomes) || !array_is_list($biomes)) {
            throw new RuntimeException('« biomes » doit être une liste.');
        }
        foreach ($biomes as $biome) {
            $type = $biome['type'] ?? null;
            if (!is_string($biome['name'] ?? null) || !is_array($type) || !is_string($type['name'] ?? null)
                || !is_array($biome['zone'] ?? null) || !is_array($biome['populations'] ?? [])
                || !self::crossingIsScalar($biome) || !self::crossingIsScalar($type)
            ) {
                throw new RuntimeException('Biome incomplet dans le bundle : nom, type et zone sont attendus.');
            }
            if (!preg_match('/^' . BiomeAdminService::TYPE_NAME_PATTERN . '$/', $type['name'])) {
                throw new RuntimeException('Nom de type de biome invalide : ' . $type['name']);
            }
        }

        return $biomes;
    }

    /**
     * Replace the plan's biomes with the bundle's. Cells are not computed
     * here: the import recomputes them once the ground is in place.
     *
     * @param list<array<string, mixed>> $biomes
     * @return list<string> what could not be imported, for the report
     */
    public function replace(string $plan, array $biomes): array
    {
        $planId = $this->db->fetchOne('SELECT id FROM plans WHERE slug = ?', [$plan]);
        if ($planId === false) {
            return ['Biomes non importés : le plan ' . $plan . ' n\'existe pas.'];
        }
        $races = array_map('intval', $this->db->fetchAllKeyValue('SELECT name, id FROM races'));

        $skipped = [];
        $this->db->transactional(function (Connection $db) use ($planId, $biomes, $races, &$skipped): void {
            $db->delete('biomes', ['plan_id' => $planId]);
            foreach ($biomes as $biome) {
                $db->insert('biomes', [
                    'plan_id' => $planId,
                    'biome_type_id' => $this->typeId($db, $biome['type']),
                    'name' => (string) $biome['name'],
                    'zone' => json_encode($biome['zone']),
                    'stack_order' => (int) ($biome['stack_order'] ?? 0),
                    // A bundle without a seed: the biome draws its own, as a new one does
                    'seed' => (int) ($biome['seed'] ?? 0) ?: random_int(1, PHP_INT_MAX >> 32),
                ] + self::crossingFrom($biome));
                $biomeId = (int) $db->lastInsertId();
                foreach ($biome['populations'] ?? [] as $population) {
                    $raceId = $races[(string) ($population['race'] ?? '')] ?? null;
                    if ($raceId === null) {
                        $skipped[] = $biome['name'] . ' : type inconnu ici, ' . ($population['race'] ?? '?') . ', non repris.';
                        continue;
                    }
                    $db->insert('biome_populations', [
                        'biome_id' => $biomeId,
                        'race_id' => $raceId,
                        'chance' => max(1, (int) ($population['chance'] ?? 1)),
                        'density' => (float) ($population['density'] ?? 0),
                        'z_min' => (int) ($population['z_min'] ?? 0),
                        'z_max' => (int) ($population['z_max'] ?? 0),
                    ]);
                }
            }
        });

        return $skipped;
    }

    /**
     * The target's type of that name; created with the bundle's label, colour and grounds when missing.
     *
     * @param array<string, mixed> $type
     */
    private function typeId(Connection $db, array $type): int
    {
        $id = $db->fetchOne('SELECT id FROM biome_types WHERE name = ?', [$type['name']]);
        if ($id !== false) {
            return (int) $id;
        }
        $db->insert('biome_types', [
            'name' => $type['name'],
            'label' => (string) ($type['label'] ?? $type['name']),
            'color' => (string) ($type['color'] ?? '#888888'),
            'patch_scale' => (int) ($type['patch_scale'] ?? BiomeAdminService::DEFAULT_PATCH_SCALE),
            'edge_width' => (int) ($type['edge_width'] ?? BiomeAdminService::DEFAULT_EDGE_WIDTH),
        ] + self::crossingFrom($type));
        $id = (int) $db->lastInsertId();
        // A terrain this server does not know would match no tile, and nothing would grow
        $known = $db->fetchFirstColumn('SELECT name FROM terrain_colors WHERE layer = ?', [TerrainTransitionService::GROUND_LAYER]);
        foreach (array_intersect_key((array) ($type['grounds'] ?? []), array_flip($known)) as $terrain => $weight) {
            $db->insert('biome_type_grounds', ['biome_type_id' => $id, 'terrain' => (string) $terrain, 'weight' => max(1, (int) $weight)]);
        }

        return $id;
    }
}
