<?php

namespace App\Service\Map;

use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;

/**
 * Biome types and biomes, as the admin edits them. Every write to a
 * biome recomputes its plan's cells.
 */
final class BiomeAdminService
{
    /** A type's technical name; the admin form checks it with the same pattern */
    public const TYPE_NAME_PATTERN = '[a-z0-9_]{1,64}';

    private Connection $db;

    public function __construct(?Connection $db = null)
    {
        $this->db = $db ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** @return list<array{id: int, name: string, label: string, color: string, biomes: int}> */
    public function types(): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'label' => (string) $row['label'],
            'color' => (string) $row['color'],
            'biomes' => (int) $row['biomes'],
        ], $this->db->fetchAllAssociative(
            'SELECT t.id, t.name, t.label, t.color, COUNT(b.id) AS biomes
             FROM biome_types t LEFT JOIN biomes b ON b.biome_type_id = t.id
             GROUP BY t.id ORDER BY t.label'
        ));
    }

    /** Create the type when $id is 0, update it otherwise; returns its id. */
    public function saveType(int $id, string $name, string $label, string $color): int
    {
        $name = trim($name);
        $label = trim($label);
        if (!preg_match('/^' . self::TYPE_NAME_PATTERN . '$/', $name)) {
            throw new InvalidArgumentException('Nom technique : minuscules, chiffres et _ seulement (ex. foret_dense).');
        }
        if ($label === '') {
            throw new InvalidArgumentException('Le libellé est obligatoire.');
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            throw new InvalidArgumentException('Couleur attendue au format #rrggbb.');
        }

        $taken = $this->db->fetchOne('SELECT id FROM biome_types WHERE name = ? AND id <> ?', [$name, $id]);
        if ($taken !== false) {
            throw new InvalidArgumentException('Un type porte déjà le nom ' . $name . '.');
        }

        $row = ['name' => $name, 'label' => $label, 'color' => strtolower($color)];
        if ($id === 0) {
            $this->db->insert('biome_types', $row);
            return (int) $this->db->lastInsertId();
        }
        $this->db->update('biome_types', $row, ['id' => $id]);

        return $id;
    }

    public function deleteType(int $id): void
    {
        if ((int) $this->db->fetchOne('SELECT COUNT(*) FROM biomes WHERE biome_type_id = ?', [$id]) > 0) {
            throw new InvalidArgumentException('Des biomes utilisent encore ce type : supprimez-les ou changez leur type d\'abord.');
        }
        $this->db->delete('biome_types', ['id' => $id]);
    }

    /**
     * @return list<array{id: int, plan: string, type: string, color: string, name: string,
     *     zone: string, stack_order: int, cells: int}>
     */
    public function biomes(string $plan = ''): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT b.id, p.slug AS plan, t.label AS type, t.color, b.name, b.zone, b.stack_order,
                    (SELECT COUNT(*) FROM biome_cells c WHERE c.biome_id = b.id) AS cells
             FROM biomes b
             JOIN plans p ON p.id = b.plan_id
             JOIN biome_types t ON t.id = b.biome_type_id
             WHERE ? = \'\' OR p.slug = ?
             ORDER BY p.slug, b.stack_order DESC, b.name',
            [$plan, $plan]
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'plan' => (string) $row['plan'],
            'type' => (string) $row['type'],
            'color' => (string) $row['color'],
            'name' => (string) $row['name'],
            'zone' => self::zoneText((array) json_decode((string) $row['zone'], true)),
            'stack_order' => (int) $row['stack_order'],
            'cells' => (int) $row['cells'],
        ], $rows);
    }

    /**
     * Create the biome when $id is 0, update it otherwise, then recompute
     * the cells of its plan (and of its old plan if it moved).
     *
     * @return array{id: int, cells: int} the biome and how many cells it covers
     */
    public function saveBiome(int $id, string $plan, int $typeId, string $name, string $zoneText, int $stackOrder): array
    {
        $planId = $this->db->fetchOne('SELECT id FROM plans WHERE slug = ?', [$plan]);
        if ($planId === false) {
            throw new InvalidArgumentException('Plan inconnu ou sans configuration : ' . $plan . '.');
        }
        if ($this->db->fetchOne('SELECT id FROM biome_types WHERE id = ?', [$typeId]) === false) {
            throw new InvalidArgumentException('Type de biome inconnu.');
        }
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Le nom du biome est obligatoire.');
        }
        $zone = self::parseZone($zoneText);
        $crossing = self::crossingSides($zone);
        if ($crossing !== null) {
            [$first, $second] = array_map(self::sideText(...), $crossing);
            throw new InvalidArgumentException(
                'Le contour se croise : le côté ' . $first . ' coupe le côté ' . $second . '. Vérifiez l\'ordre des coins.'
            );
        }
        if (!BiomeCellService::coversACell($zone)) {
            throw new InvalidArgumentException('Cette zone ne couvre aucune case.');
        }

        $row = [
            'plan_id' => (int) $planId,
            'biome_type_id' => $typeId,
            'name' => $name,
            'zone' => json_encode($zone),
            'stack_order' => $stackOrder,
        ];
        $old = false;
        if ($id === 0) {
            $this->db->insert('biomes', $row);
            $id = (int) $this->db->lastInsertId();
        } else {
            $old = $this->db->fetchAssociative('SELECT plan_id, zone FROM biomes WHERE id = ?', [$id]);
            $this->db->update('biomes', $row, ['id' => $id]);
        }

        // Only the cells under the new and the old outline can change, on their own plans
        $outlines = [(int) $planId => [$zone]];
        if ($old !== false) {
            $outlines[(int) $old['plan_id']][] = (array) json_decode((string) $old['zone'], true);
        }
        $cells = new BiomeCellService($this->db);
        foreach ($outlines as $outlinePlan => $zones) {
            $cells->rebuild($outlinePlan, BiomeCellService::areaOf(...$zones));
        }

        return [
            'id' => $id,
            'cells' => (int) $this->db->fetchOne('SELECT COUNT(*) FROM biome_cells WHERE biome_id = ?', [$id]),
        ];
    }

    public function deleteBiome(int $id): void
    {
        $biome = $this->db->fetchAssociative('SELECT plan_id, zone FROM biomes WHERE id = ?', [$id]);
        if ($biome === false) {
            return;
        }
        // Its own cells go by cascade; the ones it hid come back with the rebuild
        $this->db->delete('biomes', ['id' => $id]);
        (new BiomeCellService($this->db))->rebuild(
            (int) $biome['plan_id'],
            BiomeCellService::areaOf((array) json_decode((string) $biome['zone'], true))
        );
    }

    /** @return array<int, string> race id => label, for what can grow (plants and resources) */
    public function growableTypes(): array
    {
        $choices = [];
        foreach ($this->db->fetchAllAssociative(
            "SELECT id, name, label, type_kind FROM races WHERE type_kind IN ('plant', 'resource') ORDER BY type_kind, label, name"
        ) as $row) {
            $choices[(int) $row['id']] = self::typeLabel($row) . ($row['type_kind'] === 'resource' ? ' (ressource)' : '');
        }

        return $choices;
    }

    /** @return list<array{id: int, race_id: int, label: string, chance: int, density: float, z_min: int, z_max: int}> */
    public function populations(int $biomeId): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'race_id' => (int) $row['race_id'],
            'label' => self::typeLabel($row),
            'chance' => (int) $row['chance'],
            'density' => (float) $row['density'],
            'z_min' => (int) $row['z_min'],
            'z_max' => (int) $row['z_max'],
        ], $this->db->fetchAllAssociative(
            'SELECT bp.*, r.name, r.label FROM biome_populations bp JOIN races r ON r.id = bp.race_id
             WHERE bp.biome_id = ? ORDER BY r.label, r.name',
            [$biomeId]
        ));
    }

    /**
     * Several types share a label (Pierre: pierre1, pierre2…): the code name tells them apart.
     *
     * @param array<string, mixed> $row races.name and races.label
     */
    private static function typeLabel(array $row): string
    {
        $label = (string) ($row['label'] ?: $row['name']);

        return strcasecmp($label, (string) $row['name']) === 0 ? $label : $label . ' · ' . $row['name'];
    }

    /** One type in one biome: added, or its numbers replaced. */
    public function savePopulation(int $biomeId, int $raceId, int $chance, float $density, int $zMin, int $zMax): void
    {
        if (!array_key_exists($raceId, $this->growableTypes())) {
            throw new InvalidArgumentException('Choisissez une plante ou une ressource.');
        }
        if ($chance < 1) {
            throw new InvalidArgumentException('La chance (1 sur N) vaut au moins 1.');
        }
        if ($density <= 0 || $density > 100) {
            throw new InvalidArgumentException('La densité va de 0,01 à 100 pour 100 cases.');
        }
        if ($zMin > $zMax) {
            throw new InvalidArgumentException('Le niveau z minimum dépasse le maximum.');
        }

        $this->db->executeStatement(
            'INSERT INTO biome_populations (biome_id, race_id, chance, density, z_min, z_max) VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE chance = VALUES(chance), density = VALUES(density),
                 z_min = VALUES(z_min), z_max = VALUES(z_max)',
            [$biomeId, $raceId, $chance, $density, $zMin, $zMax]
        );
    }

    public function deletePopulation(int $id): void
    {
        $this->db->delete('biome_populations', ['id' => $id]);
    }

    /**
     * "0,0  10,0  10,8  0,8" (spaces, line breaks or ; between corners) → [[0, 0], [10, 0], ...].
     *
     * @return list<array{int, int}>
     */
    public static function parseZone(string $text): array
    {
        $zone = [];
        foreach (preg_split('/[\s;]+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $corner) {
            if (!preg_match('/^(-?\d+),(-?\d+)$/', $corner, $m)) {
                throw new InvalidArgumentException('Coin illisible : "' . $corner . '". Format attendu : x,y (ex. 0,0 10,0 10,8 0,8).');
            }
            $zone[] = [(int) $m[1], (int) $m[2]];
        }
        if (count($zone) < 3) {
            throw new InvalidArgumentException('Une zone a besoin d\'au moins trois coins.');
        }

        return $zone;
    }

    /**
     * The first two sides of the outline that cross each other, null when
     * none do. Sides that only touch, or run along each other (the way in
     * and out of a clearing), do not count.
     *
     * @param list<array{int, int}> $zone
     * @return array{array{array{int, int}, array{int, int}}, array{array{int, int}, array{int, int}}}|null
     */
    private static function crossingSides(array $zone): ?array
    {
        $sides = [];
        foreach ($zone as $i => $corner) {
            $sides[] = [$corner, $zone[($i + 1) % count($zone)]];
        }

        // ponytail: every pair of sides, fine for the hundreds of corners an outline has
        $count = count($sides);
        for ($i = 0; $i < $count; $i++) {
            [$a, $b] = $sides[$i];
            for ($j = $i + 1; $j < $count; $j++) {
                [$c, $d] = $sides[$j];
                // Each side has the other's two ends strictly on opposite sides of it
                if (self::turn($a, $b, $c) * self::turn($a, $b, $d) < 0
                    && self::turn($c, $d, $a) * self::turn($c, $d, $b) < 0) {
                    return [[$a, $b], [$c, $d]];
                }
            }
        }

        return null;
    }

    /**
     * Which way the path $from → $to → $point turns: positive left,
     * negative right, 0 when the three are aligned.
     *
     * @param array{int, int} $from
     * @param array{int, int} $to
     * @param array{int, int} $point
     */
    private static function turn(array $from, array $to, array $point): int
    {
        return ($to[0] - $from[0]) * ($point[1] - $from[1]) - ($to[1] - $from[1]) * ($point[0] - $from[0]);
    }

    /** @param array{array{int, int}, array{int, int}} $side "x,y → x,y" */
    private static function sideText(array $side): string
    {
        return implode(' → ', array_map(static fn (array $corner): string => $corner[0] . ',' . $corner[1], $side));
    }

    /** @param array<mixed> $zone */
    private static function zoneText(array $zone): string
    {
        return implode('  ', array_map(
            static fn ($p): string => is_array($p) ? (int) ($p[0] ?? 0) . ',' . (int) ($p[1] ?? 0) : '',
            $zone
        ));
    }
}
