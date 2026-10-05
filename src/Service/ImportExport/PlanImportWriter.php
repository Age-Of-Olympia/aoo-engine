<?php

namespace App\Service\ImportExport;

use App\Service\Map\StructureTypeService;
use App\Service\TiledMapService;
use Classes\Db;
use Doctrine\DBAL\Connection;

/**
 * The writes a plan import performs, one step at a time.
 *
 * Extracted from {@see PlanImporter} so the step runner
 * ({@see PlanImportRun}) can commit them one by one. Each one is
 * idempotent: a step replayed after a crash writes the same thing, never
 * twice — cells go in with INSERT IGNORE (the plan/z/x/y key), rows land
 * on a layer the purge step emptied, entities and buildings are compared
 * rather than recreated.
 */
final class PlanImportWriter
{
    /** Rows per INSERT (same value as TiledMapService). */
    private const INSERT_BATCH = 500;

    private ?Db $db = null;

    public function __construct(private Connection $conn, private ImportReport $report)
    {
    }

    /**
     * Authored rows go; player rows stay. Entities stay too, except on the
     * levels the bundle no longer has: the entity bands only cover its levels.
     *
     * @param list<int> $levels the levels of the bundle
     */
    public function purgeAuthoredRows(string $plan, array $levels): void
    {
        foreach (array_keys(TiledMapService::ENTITY_LAYERS) as $layer) {
            $reconciler = TiledMapService::reconcilerFor($layer);
            $stale = $this->conn->fetchFirstColumn(
                'SELECT DISTINCT c.z FROM players p JOIN coords c ON c.id = p.coords_id
                  WHERE p.player_type = ? AND c.plan = ?',
                [TiledMapService::ENTITY_LAYERS[$layer], $plan]
            );
            foreach (array_diff(array_map('intval', $stale), $levels) as $z) {
                $reconciler->reconcile($plan, [], $z);
            }
        }

        foreach (array_keys(TiledMapService::AUTHORABLE_LAYERS) as $layer) {
            if (isset(TiledMapService::ENTITY_LAYERS[$layer])) {
                continue;
            }

            $playerFilter = in_array('player_id', TiledMapService::AUTHORABLE_LAYERS[$layer]['columns'], true)
                ? ' AND (m.player_id IS NULL OR m.player_id = 0)'
                : '';
            $this->db()->exe(
                'DELETE m FROM map_' . $layer . ' m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?' . $playerFilter,
                array($plan)
            );
        }
    }

    /**
     * Every cell the bundle names, in a fixed order: the step list must not
     * depend on what the database already holds, or a resumed run would cut
     * its chunks elsewhere and the cursor would point at another step.
     *
     * @param array<string, mixed> $payload
     * @param array<string, list<array<string, mixed>>> $layers
     * @return list<array{0:int,1:int,2:int}>
     */
    public static function neededCoords(array $payload, array $layers): array
    {
        $needed = [];
        foreach ($payload['coords'] as [$x, $y, $z]) {
            $needed[$x . '|' . $y . '|' . $z] = [(int) $x, (int) $y, (int) $z];
        }
        foreach ($layers + ['buildings' => $payload['buildings'] ?? []] as $rows) {
            foreach ($rows as $row) {
                $key = (int) $row['x'] . '|' . (int) $row['y'] . '|' . (int) $row['z'];
                $needed[$key] ??= [(int) $row['x'], (int) $row['y'], (int) $row['z']];
            }
        }

        ksort($needed);

        return array_values($needed);
    }

    /** @param list<array{0:int,1:int,2:int}> $chunk */
    public function insertCoords(string $plan, array $chunk): void
    {
        if ($chunk === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($chunk), '(?, ?, ?, ?)'));
        $params = [];
        foreach ($chunk as [$x, $y, $z]) {
            array_push($params, $x, $y, $z, $plan);
        }

        // The (plan, z, x, y) key makes the replay a no-op.
        $this->db()->exe('INSERT IGNORE INTO coords (x, y, z, plan) VALUES ' . $placeholders, $params);
    }

    /** @param list<array<string, mixed>> $rows */
    public function insertLayerRows(string $plan, string $layer, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $coordsIds = $this->coordsIds($plan, $rows);

        // Uniform columns per layer (multi-row INSERT): the portable extras
        // of AUTHORABLE_LAYERS, with the schema defaults
        $extras = array_values(array_filter(
            TiledMapService::AUTHORABLE_LAYERS[$layer]['columns'],
            static fn(string $column): bool => $column !== 'player_id' && $column !== 'endTime'
        ));

        $columnSql = '(coords_id, `name`' . ($extras !== [] ? ', `' . implode('`, `', $extras) . '`' : '') . ')';
        $rowPlaceholder = '(' . implode(', ', array_fill(0, 2 + count($extras), '?')) . ')';

        foreach (array_chunk($rows, self::INSERT_BATCH) as $batch) {
            $params = [];
            foreach ($batch as $row) {
                $params[] = $coordsIds[(int) $row['x'] . '|' . (int) $row['y'] . '|' . (int) $row['z']];
                $params[] = (string) $row['name'];
                foreach ($extras as $column) {
                    $params[] = $this->extraValue($layer, $column, $row);
                }
            }
            $this->db()->exe(
                'INSERT INTO map_' . $layer . ' ' . $columnSql . ' VALUES '
                    . implode(', ', array_fill(0, count($batch), $rowPlaceholder)),
                $params
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function placeBuildings(string $plan, int $z, array $rows, ?int $yFrom = null, ?int $yTo = null): void
    {
        foreach ((new TiledMapService())->importBuildingsAt($plan, $z, $rows, $yFrom, $yTo) as $refused) {
            $this->report->warn($plan, 'Bâtiment non posé : ' . $refused);
        }
    }

    /** Portable value of an extra column, defaults aligned on the schema. */
    private function extraValue(string $layer, string $column, array $row): int|string
    {
        if ($column === 'damages') {
            // Same authored default as TiledMapService::insertRows(): -1
            // (harvestable) for catalog resources, 0 otherwise
            return isset($row['damages']) && is_numeric($row['damages'])
                ? (int) $row['damages']
                : (StructureTypeService::isHarvestable((string) $row['name']) ? -1 : 0);
        }
        if ($column === 'foreground' || $column === 'rotation') {
            return isset($row[$column]) && is_numeric($row[$column]) ? (int) $row[$column] : 0;
        }

        // params (plants, triggers, dialogs)
        return (string) ($row[$column] ?? '');
    }

    /**
     * "x|y|z" => coords_id of exactly the cells these rows name, never the
     * whole plan: a sparse layer (marks, foregrounds) spans every row of the map.
     *
     * @param list<array<string, mixed>> $rows
     * @return array<string, int>
     */
    private function coordsIds(string $plan, array $rows): array
    {
        $params = [$plan];
        foreach ($rows as $row) {
            array_push($params, (int) $row['z'], (int) $row['x'], (int) $row['y']);
        }

        $ids = [];
        foreach ($this->conn->fetchAllAssociative(
            'SELECT id, x, y, z FROM coords WHERE plan = ? AND (z, x, y) IN ('
                . implode(', ', array_fill(0, count($rows), '(?, ?, ?)')) . ')',
            $params
        ) as $cell) {
            $ids[$cell['x'] . '|' . $cell['y'] . '|' . $cell['z']] = (int) $cell['id'];
        }

        return $ids;
    }

    private function db(): Db
    {
        return $this->db ??= new Db();
    }
}
