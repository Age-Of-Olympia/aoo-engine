<?php

namespace App\Service\ImportExport;

use App\Service\PlanAdminService;
use App\Service\PlanConfigService;
use App\Service\Map\StructureTypeService;
use App\Service\TiledMapService;
use Classes\Db;
use Doctrine\DBAL\Connection;
use RuntimeException;

/**
 * Imports plan bundles ({@see PlanExporter}) as create-or-replace: a missing
 * plan is created, an existing one is replaced — but only its authored
 * content. Player-built rows (player_id) and map_items (runtime loot) are
 * never touched, existing coords are kept (the FKs pointing at them —
 * players, logs — stay valid), missing ones are created.
 *
 * map_* writes go through Classes\Db, which wraps the same native
 * connection as DBAL: the skeleton's transaction covers them.
 *
 * The plan's JSON file is replaced AFTER the commit: a database imported
 * without its JSON is fixed by importing again, the reverse is not.
 */
final class PlanImporter extends AbstractDbalImporter
{
    /** Multi-row INSERT batch size (same value as TiledMapService). */
    private const INSERT_BATCH = 500;

    private ?Db $db;
    private ?PlanConfigService $planConfig;
    private ?PlanAdminService $planAdmin;

    public function __construct(?Db $db = null, ?PlanConfigService $planConfig = null, ?PlanAdminService $planAdmin = null)
    {
        parent::__construct();
        // Lazy: instantiation must not open a DB connection
        $this->db = $db;
        $this->planConfig = $planConfig;
        $this->planAdmin = $planAdmin;
    }

    public function objectType(): string
    {
        return 'plan';
    }

    /** JSON after the commit (files do not roll back). */
    protected function afterImport(array $payloads): void
    {
        foreach ($payloads as $payload) {
            if (is_array($payload['config'])) {
                ($this->planConfig ??= new PlanConfigService())->replace($payload['plan'], $payload['config']);
            }
        }
    }

    /**
     * Validates and classifies each payload (create/update/reject/warn)
     * without writing anything.
     *
     * @param array<int, mixed> $objects
     * @return list<array{plan: string, config: ?array, coords: list<array{0:int,1:int,2:int}>, layers: array<string, list<array<string, mixed>>>, buildings: ?list<array<string, mixed>>}>
     */
    protected function collect(array $objects, ImportReport $report): array
    {
        $payloads = [];
        $seen = [];

        foreach ($objects as $index => $object) {
            $label = is_array($object) && is_string($object['plan'] ?? null)
                ? $object['plan']
                : 'objet #' . $index;

            try {
                $payload = $this->validate($object);
            } catch (RuntimeException $e) {
                $report->reject($label, $e->getMessage());
                continue;
            }

            if ($this->isDuplicate($report, $seen, $payload['plan'])) {
                continue;
            }

            $this->classify($payload, $report);
            $payloads[] = $payload;
        }

        return $payloads;
    }

    /**
     * @return array{plan: string, config: ?array, coords: list<array{0:int,1:int,2:int}>, layers: array<string, list<array<string, mixed>>>, buildings: ?list<array<string, mixed>>}
     * @throws RuntimeException user-facing message (French)
     */
    private function validate(mixed $object): array
    {
        if (!is_array($object)) {
            throw new RuntimeException('Le payload doit être un objet.');
        }

        $plan = $object['plan'] ?? null;
        if (!is_string($plan) || !preg_match(TiledMapService::PLAN_NAME_PATTERN, $plan)) {
            throw new RuntimeException('Nom de plan invalide (attendu : minuscules, chiffres, _ ou -, 64 max).');
        }

        $config = $object['config'] ?? null;
        if ($config !== null && !is_array($config)) {
            throw new RuntimeException('« config » doit être le JSON du plan (objet) ou null.');
        }

        $coords = [];
        foreach ((array) ($object['coords'] ?? []) as $triple) {
            if (!is_array($triple) || count($triple) !== 3
                || !is_numeric($triple[0]) || !is_numeric($triple[1]) || !is_numeric($triple[2])
            ) {
                throw new RuntimeException('« coords » doit être une liste de triplets [x, y, z].');
            }
            $coords[] = [(int) $triple[0], (int) $triple[1], (int) $triple[2]];
        }

        $layers = $object['layers'] ?? [];
        if (!is_array($layers)) {
            throw new RuntimeException('« layers » doit être un objet couche => lignes.');
        }
        // Bundles exported before the map_walls → map_resources rename
        $layers = TiledMapService::normalizeLegacyLayerKeys($layers);

        // Absent from a bundle exported before buildings travelled: left alone.
        $buildings = $layers[TiledMapService::BUILDINGS_LAYER] ?? null;
        unset($layers[TiledMapService::BUILDINGS_LAYER]);
        if ($buildings !== null) {
            if (!is_array($buildings)) {
                throw new RuntimeException('Les lignes de la couche buildings doivent être une liste.');
            }
            foreach ($buildings as $row) {
                TiledMapService::validateIncomingRow(TiledMapService::BUILDINGS_LAYER, $row);
                if (!isset($row['z']) || !is_numeric($row['z'])) {
                    throw new RuntimeException('Ligne sans z dans la couche buildings en ' . $row['x'] . ',' . $row['y']);
                }
            }
        }

        foreach ($layers as $layer => $rows) {
            if (!isset(TiledMapService::AUTHORABLE_LAYERS[$layer])) {
                throw new RuntimeException('Couche inconnue : ' . $layer);
            }
            if (!is_array($rows)) {
                throw new RuntimeException('Les lignes de la couche ' . $layer . ' doivent être une liste.');
            }
            foreach ($rows as $row) {
                TiledMapService::validateIncomingRow($layer, $row);
                if (!isset($row['z']) || !is_numeric($row['z'])) {
                    throw new RuntimeException('Ligne sans z dans la couche ' . $layer . ' en ' . $row['x'] . ',' . $row['y']);
                }
            }
        }
        // A layer missing from the bundle is an empty layer: the bundle carries the whole state
        foreach (array_keys(TiledMapService::AUTHORABLE_LAYERS) as $layer) {
            $layers[$layer] ??= [];
        }

        return ['plan' => $plan, 'config' => $config, 'coords' => $coords, 'layers' => $layers, 'buildings' => $buildings];
    }

    /** @param array{plan: string, layers: array<string, array>, buildings: ?list<array<string, mixed>>} $payload */
    private function classify(array $payload, ImportReport $report): void
    {
        $plan = $payload['plan'];
        $planAdmin = $this->planAdmin ??= new PlanAdminService();

        foreach (array_keys(TiledMapService::ENTITY_LAYERS) as $layer) {
            $unknown = TiledMapService::reconcilerFor($layer)->unknownTypes($payload['layers'][$layer]);
            if ($unknown !== []) {
                $report->warn($plan, 'Types absents du catalogue, non posés (' . $layer . ') : ' . implode(', ', $unknown) . '.');
            }
        }

        if (!$planAdmin->planExists($plan)) {
            $report->addCreated($plan);
            return;
        }

        $report->addUpdated($plan);

        $characters = $planAdmin->countCharactersOnPlan($plan);
        if ($characters['players'] > 0) {
            $report->warn($plan, $characters['players'] . ' joueur(s) se trouvent sur ce plan — son contenu va changer sous leurs pieds.');
        }

        $playerBuilt = $this->countPlayerBuiltRows($plan);
        if ($playerBuilt > 0) {
            $report->warn($plan, $playerBuilt . ' construction(s) de joueurs sur ce plan (préservées, hors import).');
        }
    }

    /**
     * Replaces a plan's authored content with the payload's, inside the
     * batch transaction.
     *
     * @param array{plan: string, coords: list<array{0:int,1:int,2:int}>, layers: array<string, list<array<string, mixed>>>, buildings: ?list<array<string, mixed>>} $payload
     */
    protected function apply(Connection $conn, array $payload, ImportReport $report): void
    {
        $plan = $payload['plan'];
        $db = $this->db();

        // 1. Purge the authored content (player rows stay)
        foreach (array_keys(TiledMapService::AUTHORABLE_LAYERS) as $layer) {
            if (isset(TiledMapService::ENTITY_LAYERS[$layer])) {
                continue;
            }

            $playerFilter = in_array('player_id', TiledMapService::AUTHORABLE_LAYERS[$layer]['columns'], true)
                ? ' AND (m.player_id IS NULL OR m.player_id = 0)'
                : '';
            $db->exe(
                'DELETE m FROM map_' . $layer . ' m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?' . $playerFilter,
                array($plan)
            );
        }

        // 2. Coords: the payload's plus the rows', created in batches
        $needed = [];
        foreach ($payload['coords'] as [$x, $y, $z]) {
            $needed[$x . '|' . $y . '|' . $z] = [$x, $y, $z];
        }
        foreach ($payload['layers'] + ['buildings' => $payload['buildings'] ?? []] as $rows) {
            foreach ($rows as $row) {
                $key = (int) $row['x'] . '|' . (int) $row['y'] . '|' . (int) $row['z'];
                $needed[$key] ??= [(int) $row['x'], (int) $row['y'], (int) $row['z']];
            }
        }

        $coordsIds = $this->loadCoordsIds($plan);
        $missing = array_diff_key($needed, $coordsIds);
        foreach (array_chunk(array_values($missing), self::INSERT_BATCH) as $chunk) {
            $placeholders = implode(', ', array_fill(0, count($chunk), '(?, ?, ?, ?)'));
            $params = [];
            foreach ($chunk as [$x, $y, $z]) {
                array_push($params, $x, $y, $z, $plan);
            }
            $db->exe('INSERT INTO coords (x, y, z, plan) VALUES ' . $placeholders, $params);
        }
        if ($missing !== []) {
            $coordsIds = $this->loadCoordsIds($plan);
        }

        // 3. Insert the layers in batches
        foreach ($payload['layers'] as $layer => $rows) {
            if (isset(TiledMapService::ENTITY_LAYERS[$layer])) {
                continue;
            }

            $this->insertLayerRows($layer, $rows, $coordsIds);
        }

        /* 4. Resources, plants and roads are entities: COMPARE instead of
         * replacing. One the bundle redraws identically keeps its id and its
         * state — exhausted, it stays so and regrows in its own time. The
         * reconciler writes on the Doctrine connection, the one Classes\Db
         * wraps: same transaction, same rollback. */
        foreach (array_keys(TiledMapService::ENTITY_LAYERS) as $layer) {
            /* No level: a bundle redraws the whole plan. */
            // Unknown types were reported by classify(): same rows, same answer.
            TiledMapService::reconcilerFor($layer)->reconcile($plan, $payload['layers'][$layer] ?? []);
        }

        if ($payload['buildings'] !== null) {
            foreach ((new TiledMapService())->importDecorBuildings($plan, $payload['buildings']) as $refused) {
                $report->warn($plan, 'Bâtiment non posé : ' . $refused);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, int>         $coordsIds "x|y|z" => id
     */
    private function insertLayerRows(string $layer, array $rows, array $coordsIds): void
    {
        if ($rows === []) {
            return;
        }

        // Uniform columns per layer (multi-row INSERT): the portable extras
        // of AUTHORABLE_LAYERS, with the schema defaults
        $extras = array_values(array_filter(
            TiledMapService::AUTHORABLE_LAYERS[$layer]['columns'],
            fn(string $column) => $column !== 'player_id' && $column !== 'endTime'
        ));

        $columnSql = '(coords_id, `name`' . ($extras !== [] ? ', `' . implode('`, `', $extras) . '`' : '') . ')';
        $rowPlaceholder = '(' . implode(', ', array_fill(0, 2 + count($extras), '?')) . ')';

        foreach (array_chunk($rows, self::INSERT_BATCH) as $chunk) {
            $params = [];
            foreach ($chunk as $row) {
                $params[] = $coordsIds[(int) $row['x'] . '|' . (int) $row['y'] . '|' . (int) $row['z']];
                $params[] = (string) $row['name'];
                foreach ($extras as $column) {
                    $params[] = $this->extraValue($layer, $column, $row);
                }
            }
            $this->db()->exe(
                'INSERT INTO map_' . $layer . ' ' . $columnSql . ' VALUES '
                    . implode(', ', array_fill(0, count($chunk), $rowPlaceholder)),
                $params
            );
        }
    }

    /** Portable value of an extra column, defaults aligned on the schema / insertRows(). */
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

    /** @return array<string, int> "x|y|z" => coords_id of the whole plan */
    private function loadCoordsIds(string $plan): array
    {
        $res = $this->db()->exe('SELECT id, x, y, z FROM coords WHERE plan = ?', array($plan));

        $coordsIds = [];
        while ($row = $res->fetch_assoc()) {
            $coordsIds[$row['x'] . '|' . $row['y'] . '|' . $row['z']] = (int) $row['id'];
        }

        return $coordsIds;
    }

    private function countPlayerBuiltRows(string $plan): int
    {
        /* What a player built is a building entity, not a layer row: without
         * this count the "préservées, hors import" warning would stay silent
         * on the only constructions left. */
        $built = $this->db()->exe(
            'SELECT COUNT(*) n FROM buildings b
               JOIN players p ON p.id = b.player_id
               JOIN coords c ON c.id = p.coords_id
              WHERE c.plan = ? AND p.owner_id IS NOT NULL AND p.owner_id <> 0',
            array($plan)
        );

        $total = (int) ($built->fetch_assoc()['n'] ?? 0);

        foreach (TiledMapService::AUTHORABLE_LAYERS as $layer => $spec) {
            if (isset(TiledMapService::ENTITY_LAYERS[$layer]) || !in_array('player_id', $spec['columns'], true)) {
                continue;
            }
            $res = $this->db()->exe(
                'SELECT COUNT(*) n FROM map_' . $layer . ' m
                 JOIN coords c ON c.id = m.coords_id
                 WHERE c.plan = ? AND m.player_id IS NOT NULL AND m.player_id <> 0',
                array($plan)
            );
            $total += (int) ($res->fetch_assoc()['n'] ?? 0);
        }

        return $total;
    }

    private function db(): Db
    {
        return $this->db ??= new Db();
    }
}
