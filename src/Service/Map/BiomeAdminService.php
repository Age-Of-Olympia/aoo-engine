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
        if (!preg_match('/^[a-z0-9_]{1,64}$/', $name)) {
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
        if (BiomeCellService::cellsInside($zone) === []) {
            throw new InvalidArgumentException('Cette zone ne couvre aucune case.');
        }

        $row = [
            'plan_id' => (int) $planId,
            'biome_type_id' => $typeId,
            'name' => $name,
            'zone' => json_encode($zone),
            'stack_order' => $stackOrder,
        ];
        $oldPlan = null;
        if ($id === 0) {
            $this->db->insert('biomes', $row);
            $id = (int) $this->db->lastInsertId();
        } else {
            $oldPlan = $this->db->fetchOne('SELECT plan_id FROM biomes WHERE id = ?', [$id]);
            $this->db->update('biomes', $row, ['id' => $id]);
        }

        $cells = new BiomeCellService($this->db);
        $cells->rebuild((int) $planId);
        if ($oldPlan !== false && $oldPlan !== null && (int) $oldPlan !== (int) $planId) {
            $cells->rebuild((int) $oldPlan);
        }

        return [
            'id' => $id,
            'cells' => (int) $this->db->fetchOne('SELECT COUNT(*) FROM biome_cells WHERE biome_id = ?', [$id]),
        ];
    }

    public function deleteBiome(int $id): void
    {
        $planId = $this->db->fetchOne('SELECT plan_id FROM biomes WHERE id = ?', [$id]);
        if ($planId === false) {
            return;
        }
        // Its own cells go by cascade; the ones it hid come back with the rebuild
        $this->db->delete('biomes', ['id' => $id]);
        (new BiomeCellService($this->db))->rebuild((int) $planId);
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

    /** @param array<mixed> $zone */
    private static function zoneText(array $zone): string
    {
        return implode('  ', array_map(
            static fn ($p): string => is_array($p) ? (int) ($p[0] ?? 0) . ',' . (int) ($p[1] ?? 0) : '',
            $zone
        ));
    }
}
