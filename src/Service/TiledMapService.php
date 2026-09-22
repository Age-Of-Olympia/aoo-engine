<?php

namespace App\Service;

use Classes\Db;
use Classes\View;
use RuntimeException;

/**
 * Export / import of game plans for the Tiled extension (aoo-tiled-extension repo).
 *
 * Holds the transactional diff engine over the map_* tables and composes two
 * neighbours: TileCatalogService (images under img/) and PlanConfigService
 * (plan JSON).
 *
 * An exported "plan" is the authorable layers of one (plan, z). map_items
 * never appears: it is runtime state (items on the ground).
 *
 * The import is a per-layer diff keyed on (x, y, name[, params]):
 *  - identical rows are kept as they are, runtime columns included
 *    (endTime of elements…);
 *  - player-built rows (player_id set) are untouchable: never deleted,
 *    ignored by the diff;
 *  - the whole thing is transactional, with optimistic version control:
 *    the version computed at pull time must match the current state, else
 *    409 (another admin — or the game — changed the plan).
 *
 * The "buildings" layer is special: it has no map_* table, its rows are the
 * building ENTITIES of the level (players of type building). Exported as one
 * tile per entity (name = the structure type / race); imported through the
 * same (x, y, name) diff, but every placement is a BuildingService::place()
 * and every removal a remove() — outside the map_* transaction (other
 * connection), after it, one by one: an occupied cell is reported as
 * "skipped" without failing the push. Only DECOR is diffable (no owner, no
 * faction, state built); the rest — player buildings, faction outposts,
 * building sites, ruins — is protected like the player_id rows of the other
 * layers.
 */
class TiledMapService
{
    /** The one rule for plan names (endpoints: tiledValidPlanName()) */
    public const PLAN_NAME_PATTERN = '/^[a-z0-9_-]{1,64}$/';

    /**
     * Specification of the authorable layers.
     *  - columns: columns exported on top of name/x/y;
     *  - paramsInKey: params is part of the identity key (authored content —
     *    changing a trigger's params = delete + insert). On the other layers
     *    the non-key columns are runtime state, preserved on kept rows;
     *  - composites: the layer accepts multi-tile structures (the ground
     *    stays strictly 50x50);
     *  - permanentOnly: the editor only sees rows without an expiry
     *    (endTime 0). Dated ones — blood, footprints — belong to the game:
     *    neither pulled, nor counted in the version, nor erased by a push.
     */
    public const AUTHORABLE_LAYERS = [
        'tiles'       => ['columns' => ['foreground', 'player_id', 'rotation'], 'paramsInKey' => false, 'composites' => false],
        'routes'      => ['columns' => [],                          'paramsInKey' => false, 'composites' => true],
        'plants'      => ['columns' => [],                          'paramsInKey' => false, 'composites' => true],
        'resources'   => ['columns' => [],                          'paramsInKey' => false, 'composites' => true],
        'elements'    => ['columns' => ['endTime', 'rotation'],     'paramsInKey' => false, 'composites' => true, 'permanentOnly' => true],
        'marks'       => ['columns' => ['endTime'],                 'paramsInKey' => false, 'composites' => false, 'permanentOnly' => true],
        'foregrounds' => ['columns' => [],                          'paramsInKey' => false, 'composites' => true],
        'triggers'    => ['columns' => ['params'],                  'paramsInKey' => true,  'composites' => false],
        'dialogs'     => ['columns' => ['params'],                  'paramsInKey' => true,  'composites' => false],
    ];

    /** Turn angles a placed tile or element may carry (map_*.rotation). */
    public const ROTATIONS = [0, 90, 180, 270];

    /** A layer whose rows carry a rotation: the angle is part of what is placed. */
    public static function hasRotation(string $layer): bool
    {
        return in_array('rotation', self::AUTHORABLE_LAYERS[$layer]['columns'] ?? [], true);
    }

    /** The SQL that keeps a layer's runtime rows away from the editor. */
    public static function authoredRowsClause(array $spec): string
    {
        return !empty($spec['permanentOnly']) ? ' AND m.endTime = 0' : '';
    }

    /**
     * The layers whose rows are ENTITIES, with their family.
     *
     * Their map_* table is empty and has no reader since the conversion: a
     * resource is a player of type `resource`, with its cell, its state
     * satellite and its regrowth; a road is a player of type `route`
     * (Version20260831140000). They stay out of the row diff — which would
     * throw that identity away — and go through {@see ResourceReconciler},
     * as the bundle import does ({@see \App\Service\ImportExport\PlanImporter}).
     *
     * The pull reads them from the same place: read from map_*, the layer
     * would show an empty plan, and whatever a push wrote there would reach
     * nobody.
     */
    public const ENTITY_LAYERS = ['resources' => 'resource', 'plants' => 'plant', 'routes' => 'route'];

    /** Virtual layer of the building entities (no map_* table) */
    public const BUILDINGS_LAYER = 'buildings';

    /** The layer whose rows may name a whole object rather than a piece. */
    public const SCENERY_LAYER = 'foregrounds';

    /** The entity layer that gives way to a structure on the same cell. */
    public const GROUND_ENTITY_LAYER = 'routes';

    /**
     * Structures win the cell: a road drawn where a wall or a building
     * stands is dropped, rather than the placement being refused.
     *
     * Old maps carry both on the same cell — the road was created first
     * (the reconciler places without asking), then the building was
     * refused « case occupée » and lost for good.
     *
     * @param list<array<string, mixed>> $roads     incoming rows of the routes layer
     * @param list<array<string, mixed>> $buildings incoming rows of the buildings layer
     * @return array{kept: list<array<string, mixed>>, dropped: int}
     */
    public static function roadsClearOfStructures(array $roads, array $buildings): array
    {
        if ($roads === [] || $buildings === []) {
            return ['kept' => array_values($roads), 'dropped' => 0];
        }

        $footprints = (new \App\Service\Map\EntityTypeFootprintService())->catalogue();
        $taken = [];
        foreach ($buildings as $row) {
            $x = (int) $row['x'];
            $y = (int) $row['y'];
            $z = (int) ($row['z'] ?? 0);
            $footprint = $footprints[(string) $row['name']] ?? null;
            $cells = $footprint === null
                ? [[$x, $y]]
                : $footprint->cellsAround((int) array_key_first($footprint->offsets()), $x, $y);

            foreach ($cells as [$cellX, $cellY]) {
                $taken[$cellX . '|' . $cellY . '|' . $z] = true;
            }
        }

        $kept = [];
        $dropped = 0;
        foreach ($roads as $row) {
            if (isset($taken[(int) $row['x'] . '|' . (int) $row['y'] . '|' . (int) ($row['z'] ?? 0)])) {
                $dropped++;
                continue;
            }
            $kept[] = $row;
        }

        return ['kept' => $kept, 'dropped' => $dropped];
    }

    /**
     * Image directory of a layer. The "resources" layer (ex-walls) keeps
     * img/walls: the asset repo is not versioned here and the converted
     * entities' avatars point to img/walls/… paths copied into the database —
     * renaming the folder would break both.
     */
    public static function layerImageDir(string $layer): string
    {
        return $layer === 'resources' ? 'walls' : $layer;
    }

    public const TILE_SIZE = 50;

    /**
     * Write batch size (same value as PlanImporter).
     *
     * One row per query only holds for a few hundred rows; an area paste
     * brings tens of thousands and blows PHP's time or memory limit.
     */
    private const INSERT_BATCH = 500;

    private Db $db;
    private TileCatalogService $catalog;
    private PlanConfigService $planConfig;

    public function __construct()
    {
        $this->db = new Db();
        $this->catalog = new TileCatalogService();
        $this->planConfig = new PlanConfigService();
    }

    /** @return array|null null when the (plan, z) does not exist */
    public function exportPlan(string $plan, int $z): ?array
    {
        $zLevels = $this->planZLevels($plan);

        // An empty but existing level (coords without content) stays pullable:
        // the multi-z extension must be able to show it and fill it
        if (!in_array($z, $zLevels, true)) {
            return null;
        }

        $layerNames = array_keys(self::AUTHORABLE_LAYERS);
        $compositeLayers = array_keys(array_filter(
            self::AUTHORABLE_LAYERS,
            fn(array $spec) => $spec['composites']
        ));

        $layers = $this->fetchLayers($plan, $z);
        ['catalog' => $catalog, 'images' => $images] = $this->catalog->buildCatalog($layerNames);
        $composites = $this->catalog->buildComposites($compositeLayers);

        // The palette only offers what you place in one go: the pieces of a
        // figure go beside it, in their own tileset. They keep their image —
        // a pulled plan containing them has to render, and truncated
        // instances are waiting to be repaired piece by piece.
        $pieces = [];
        $footprints = (new \App\Service\Map\EntityTypeFootprintService())->catalogue();
        foreach ($compositeLayers as $layer) {
            /* Only the scenery folder is guessed at: elsewhere a run of
             * numbered siblings is a run of VARIANTS — three stones, three
             * trees — and reading it as one figure emptied the palette. */
            $loose = $this->catalog->loosePieces(
                $layer,
                $footprints,
                $layer === self::SCENERY_LAYER
            );
            $pieces[$layer] = array_values(array_intersect($catalog[$layer] ?? [], $loose));
            $catalog[$layer] = array_values(array_diff($catalog[$layer] ?? [], $loose));
        }

        // Obstacles are building entities: the resources palette only offers
        // what still goes on that layer for this plan (resources, altars,
        // unique_* — everything on tutorial plans). Walls already placed stay
        // visible: buildLevel takes them from the plan's rows, not the catalog.
        $catalog['resources'] = ResourcePaletteService::filterNames($catalog['resources'] ?? [], $plan);

        // Same rule for triggers: the palette only offers those the game can
        // run (a handler in scripts/map/triggers). Rows already placed are
        // still pulled: one must be able to remove them.
        $catalog['triggers'] = TriggerPaletteService::filterNames($catalog['triggers'] ?? []);
        $composites['resources'] = array_values(array_filter(
            $composites['resources'] ?? [],
            fn(array $composite) => ResourcePaletteService::isAuthorable($composite['name'], $plan)
        ));

        // Buildings palette: the structure type catalog (same entries as
        // admin → Bâtiments), sprite resolved as at render time
        $catalog[self::BUILDINGS_LAYER] = [];
        foreach ((new RaceService())->getBuildingTypes() as $race) {
            $catalog[self::BUILDINGS_LAYER][] = $race->getName();
            $sprite = BuildingService::resolveAvatar($race->getName());
            if ($sprite !== BuildingService::NO_IMAGE) {
                $images[self::BUILDINGS_LAYER . '/' . $race->getName()] = $sprite;
            }
        }
        sort($catalog[self::BUILDINGS_LAYER]);

        return [
            'plan'       => $plan,
            'z'          => $z,
            'zLevels'    => $zLevels,
            'tileSize'   => self::TILE_SIZE,
            'version'    => $this->computeVersion($layers),
            'layers'     => $layers,
            'catalog'    => $catalog,
            'images'     => $images,
            'composites' => $composites,
            'pieces'     => $pieces,
            'planConfig' => [
                'values' => $this->planConfig->read($plan),
            ],
            'zConfig'    => $this->planConfig->readZLevel($plan, $z),
        ];
    }

    /**
     * The whole push use case: validates the plan configuration BEFORE the
     * transaction (no 400 possible once the layers are committed), imports
     * the layers, writes the configuration, resets the level bounds, and
     * returns the plan JSON's health report.
     *
     * @return array{layers: array, newVersion: string, planHealth?: array}
     */
    public function applyPush(string $plan, int $z, array $layers, string $expectedVersion, ?array $planConfig, ?array $zConfig): array
    {
        $parsedConfig = $planConfig !== null ? $this->planConfig->parse($planConfig) : null;

        $result = $this->importPlan($plan, $z, $layers, $expectedVersion);

        if ($parsedConfig !== null) {
            $this->planConfig->write($plan, $parsedConfig);
        }

        $this->planConfig->writeZLevel($plan, $z, $zConfig ?? [], $this->planConfig->boundsFromCoords($plan, $z));

        $health = $this->planConfig->validate($plan, $this->db, $this->knownItemNames());
        if ($health['errors'] !== [] || $health['warnings'] !== []) {
            $result['planHealth'] = $health;
        }

        $this->refreshBoardsAround($plan, $z, $layers);

        return $result;
    }

    /**
     * Redraws the boards of whoever saw the pushed area.
     *
     * The board is cached per player, without expiry: a building placed from
     * Tiled in someone's field of view would not show until they moved.
     *
     * Over the push's extent at once rather than cell by cell: a push touches
     * hundreds of cells, which would purge the same files.
     *
     * @param array<string, mixed> $layers as pushed, so not yet of a safe shape
     */
    private function refreshBoardsAround(string $plan, int $z, array $layers): void
    {
        $xs = [];
        $ys = [];

        foreach ($layers as $rows) {
            if (!is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                if (is_array($row) && isset($row['x'], $row['y'])) {
                    $xs[] = (int) $row['x'];
                    $ys[] = (int) $row['y'];
                }
            }
        }

        if ($xs === []) {
            return; /* empty push: nobody saw anything change */
        }

        /* The widest sight range, to catch whoever sees the edge of the area
         * from outside. */
        $reach = 20;

        \Classes\View::refresh_players_svg_in_box(
            min($xs) - $reach,
            max($xs) + $reach,
            min($ys) - $reach,
            max($ys) + $reach,
            $z,
            $plan
        );
    }

    /**
     * Layout data for a Tiled world: per plan, its (x, y) position on the
     * world map, its content extent per z level, and the plans its `tp`
     * triggers lead to (link graph, to place off-grid plans and flag broken
     * links).
     *
     * Plans whose name fails PLAN_NAME_PATTERN (historical residue of the
     * coords table) are left out — export.php would refuse them — and listed
     * in `ignored` for the admin.
     *
     * @return array{tileSize: int, plans: array<string, array<string, mixed>>, ignored: string[]}
     */
    public function worldLayout(): array
    {
        $plans = [];
        $ignored = [];

        // Content extent per (plan, z)
        $res = $this->db->exe(
            'SELECT plan, z, MIN(x) minX, MAX(x) maxX, MIN(y) minY, MAX(y) maxY
             FROM coords GROUP BY plan, z ORDER BY plan, z'
        );
        while ($row = $res->fetch_assoc()) {
            $plan = $row['plan'];
            if (!preg_match(self::PLAN_NAME_PATTERN, $plan)) {
                $ignored[$plan] = true;
                continue;
            }
            $z = (int) $row['z'];
            $plans[$plan]['zLevels'][] = $z;
            $plans[$plan]['bounds'][$z] = [
                'minX' => (int) $row['minX'], 'maxX' => (int) $row['maxX'],
                'minY' => (int) $row['minY'], 'maxY' => (int) $row['maxY'],
            ];
        }

        // tp links: source plan → distinct destination plans
        $links = [];
        $res = $this->db->exe(
            'SELECT c.plan AS src, t.params FROM map_triggers t
             JOIN coords c ON c.id = t.coords_id WHERE t.name = "tp"'
        );
        while ($row = $res->fetch_assoc()) {
            $dest = explode(',', (string) $row['params'])[3] ?? '';
            $dest = trim($dest);
            if ($dest !== '' && $dest !== 'plan' && $dest !== $row['src']) {
                $links[$row['src']][$dest] = true;
            }
        }

        // (x, y) position from the plan JSON + links
        foreach ($plans as $plan => &$data) {
            $position = $this->planConfig->readPosition($plan);
            $data['x'] = $position['x'];
            $data['y'] = $position['y'];
            $data['links'] = array_keys($links[$plan] ?? []);
        }

        return ['tileSize' => self::TILE_SIZE, 'plans' => $plans, 'ignored' => array_keys($ignored)];
    }

    /**
     * Existing plans — same residue left out as worldLayout(): a plan listed
     * here must always be pullable through export.php.
     *
     * @return array<string, array{zLevels: int[], coords: int}>
     */
    public function listPlans(): array
    {
        $res = $this->db->exe('SELECT plan, z, COUNT(*) AS n FROM coords GROUP BY plan, z ORDER BY plan, z');

        $plans = [];
        while ($row = $res->fetch_assoc()) {
            if (!preg_match(self::PLAN_NAME_PATTERN, $row['plan'])) {
                continue;
            }
            $plans[$row['plan']]['zLevels'][] = (int) $row['z'];
            $plans[$row['plan']]['coords'] = ((int) ($plans[$row['plan']]['coords'] ?? 0)) + (int) $row['n'];
        }

        return $plans;
    }

    /** @throws RuntimeException code 409 when the plan already exists */
    public function createPlan(string $plan): void
    {
        if ($this->planZLevels($plan) !== []) {
            throw new RuntimeException('Le plan existe déjà : ' . $plan, 409);
        }

        // One seed coordinate is enough: the plan exists, the import creates
        // the other coords as edits come
        $coordsId = View::get_coords_id((object) ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => $plan]);

        if (!$coordsId) {
            throw new RuntimeException('Création du plan impossible : ' . $plan, 500);
        }
    }

    /**
     * @param array<string, array> $incomingLayers layers sent by the extension
     * @return array{layers: array, newVersion: string}
     * @throws RuntimeException code 400 (invalid payload) or 409 (version conflict)
     */
    public function importPlan(string $plan, int $z, array $incomingLayers, string $expectedVersion): array
    {
        $incomingLayers = self::normalizeLegacyLayerKeys($incomingLayers);

        foreach (array_keys($incomingLayers) as $layer) {
            if (!isset(self::AUTHORABLE_LAYERS[$layer]) && $layer !== self::BUILDINGS_LAYER) {
                throw new RuntimeException('Couche inconnue : ' . $layer, 400);
            }
        }

        // Buildings are entities: placed/removed through BuildingService
        // (other connection), AFTER the map_* transaction — a wall deleted in
        // the same push frees its cell before the placement
        $incomingBuildings = null;
        if (array_key_exists(self::BUILDINGS_LAYER, $incomingLayers)) {
            $incomingBuildings = $incomingLayers[self::BUILDINGS_LAYER];
            unset($incomingLayers[self::BUILDINGS_LAYER]);
        }

        /* Resources, plants and roads are not rows either: they go through
         * the reconciler, outside the map_* diff. */
        $incomingEntities = [];
        foreach (array_keys(self::ENTITY_LAYERS) as $layer) {
            if (array_key_exists($layer, $incomingLayers)) {
                $incomingEntities[$layer] = $incomingLayers[$layer];
                unset($incomingLayers[$layer]);
            }
        }

        $currentLayers = $this->fetchLayers($plan, $z);

        if (!hash_equals($this->computeVersion($currentLayers), $expectedVersion)) {
            throw new RuntimeException(
                'Le plan a changé depuis le pull — refaire un pull avant de pousser.',
                409
            );
        }

        /* A composite arrives as ONE row — the object, not its pieces — and
         * the server lays the figure out. The pieces must exist before the
         * diff runs, or it would delete the ones the push did not mention. */
        if (isset($incomingLayers[self::SCENERY_LAYER])) {
            $incomingLayers[self::SCENERY_LAYER] = $this->spreadComposites(
                $incomingLayers[self::SCENERY_LAYER]
            );
        }

        /* Entity rows are validated BEFORE the transaction: they are written
         * after the layers commit, so a late refusal would leave the rest
         * applied. Same rule as the plan configuration in applyPush(). */
        $wantedEntities = [];
        foreach ($incomingEntities as $layer => $rows) {
            $wantedEntities[$layer] = $this->validateEntityRows($plan, $z, $layer, $rows);
        }

        $coordsIds = $this->loadCoordsIds($plan, $z);

        // Cells are born in one go, before the layers that land on them
        $this->ensureCoords($plan, $z, $incomingLayers, $coordsIds);

        $report = [];

        $this->db->beginTransaction();
        try {
            foreach ($incomingLayers as $layer => $rows) {
                $report[$layer] = $this->importLayer($layer, $rows, $currentLayers[$layer], $coordsIds);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        // The post-import state is known without re-reading the database:
        // imported layers are exactly the rows received (player rows are out
        // of the diff and out of the fingerprint alike), the others did not move
        $postLayers = array_merge($currentLayers, $incomingLayers);

        /* Scenery laid down by a push must become an entity, or its cut-out's
         * roles are read by nobody. Derived from what is on the map rather
         * than from what the push said, so it also catches what earlier
         * pushes left behind. */
        if (isset($incomingLayers[self::SCENERY_LAYER])) {
            $scenery = new \App\Service\Map\SceneryObjectService();
            $report[self::SCENERY_LAYER]['entities'] = $scenery->convertOrphans();
            /* The other direction: a push that erased every piece of a
             * figure leaves its entity behind, still drawn in-game from
             * entity_cells with nothing left in the editor to erase it. */
            $report[self::SCENERY_LAYER]['vanished'] = $scenery->removeOrphanedEntities();
        }

        /* Structures win the cell, here as in a bundle import: a road
         * pushed under a wall would take the cell and the wall would be
         * refused. */
        $droppedRoads = 0;
        if ($incomingBuildings !== null && isset($wantedEntities[self::GROUND_ENTITY_LAYER])) {
            $roads = self::roadsClearOfStructures($wantedEntities[self::GROUND_ENTITY_LAYER], $incomingBuildings);
            $wantedEntities[self::GROUND_ENTITY_LAYER] = $roads['kept'];
            $droppedRoads = $roads['dropped'];
        }

        /* Before the buildings: a resource removed in the same push frees
         * its cell before something else is placed on it. */
        foreach ($wantedEntities as $layer => $wanted) {
            $report[$layer] = $this->reconcileEntityLayer($plan, $z, $layer, $wanted);
            $postLayers[$layer] = self::reconcilerFor($layer)->asPayloadRows($plan, $z);
        }

        if ($droppedRoads > 0) {
            $report[self::GROUND_ENTITY_LAYER]['under_structure'] = $droppedRoads;
        }

        if ($incomingBuildings !== null) {
            $report[self::BUILDINGS_LAYER] = $this->importBuildingsLayer(
                $plan,
                $z,
                $incomingBuildings,
                $currentLayers[self::BUILDINGS_LAYER]
            );
            // Unlike map_*, the final state may differ from the rows received
            // (refused placements): re-read the actual entities
            $postLayers[self::BUILDINGS_LAYER] = $this->fetchBuildingRows($plan, $z);
        }

        return [
            'layers'     => $report,
            'newVersion' => $this->computeVersion($postLayers),
        ];
    }

    /**
     * The reconciler of an entity layer: its family, and the folder its
     * sprites live in (the layer's, through layerImageDir()).
     *
     * Public because pull, push, plan cloning and bundle import all want the
     * same one: an entity layer has ONE writer.
     */
    public static function reconcilerFor(string $layer): \App\Service\Map\ResourceReconciler
    {
        return new \App\Service\Map\ResourceReconciler(
            null,
            self::ENTITY_LAYERS[$layer],
            'img/' . self::layerImageDir($layer) . '/'
        );
    }

    /**
     * Rows of an entity layer received from the push, validated and shaped
     * for the reconciler.
     *
     * @param list<array<string, mixed>> $incomingRows
     * @return list<array{name: string, x: int, y: int, z: int}>
     * @throws RuntimeException code 400
     */
    private function validateEntityRows(string $plan, int $z, string $layer, array $incomingRows): array
    {
        $wanted = [];

        foreach ($incomingRows as $row) {
            self::validateIncomingRow($layer, $row);

            // Obstacles and decor are buildings: the resources layer only
            // receives what still goes on it.
            if ($layer === 'resources' && !ResourcePaletteService::isAuthorable((string) $row['name'], $plan)) {
                throw new RuntimeException(
                    'Mur « ' . $row['name'] . ' » en ' . $row['x'] . ',' . $row['y']
                        . ' : les obstacles se posent sur la couche buildings (ou admin → Bâtiments) — '
                        . 'la couche resources ne reçoit que les ressources récoltables, les autels et les unique_*.',
                    400
                );
            }

            $wanted[] = [
                'name' => (string) $row['name'],
                'x'    => (int) $row['x'],
                'y'    => (int) $row['y'],
                'z'    => $z,
            ];
        }

        return $wanted;
    }

    /**
     * Diff of an entity layer — resources, plants, roads.
     *
     * Same identity key as the tile layers (x, y, name), but the comparison
     * is the reconciler's: what both sides draw alike keeps its id AND its
     * state, so an exhausted resource stays exhausted and regrows in its own
     * time. What the push no longer names leaves the board, satellite
     * included.
     *
     * Restricted to the pushed level: the editor sends one z at a time, and
     * what it is not looking at must not read as "removed".
     *
     * @param list<array{name: string, x: int, y: int, z: int}> $wanted
     * @return array{inserted: int, deleted: int, kept: int, protected: int, skipped: list<string>}
     */
    private function reconcileEntityLayer(string $plan, int $z, string $layer, array $wanted): array
    {
        $result = self::reconcilerFor($layer)->reconcile($plan, $wanted, $z);

        return [
            'inserted'  => $result['created'],
            'deleted'   => $result['removed'],
            'kept'      => $result['kept'],
            'protected' => 0,
            /* A type the catalog does not know is not placed: the push says
               so, like a refused building placement. */
            'skipped'   => array_map(
                static fn(string $name): string => $name . ' — type inconnu du catalogue, non posé',
                $result['unknown']
            ),
        ];
    }

    /**
     * Diff of the buildings layer: same identity key (x, y, type) as the tile
     * layers, but every placement is a BuildingService::place() (occupancy
     * checks included) and every removal a remove(). A refused placement is
     * reported in `skipped` without failing the push; protected entities
     * (owner, faction, building site, ruin) are out of the diff like the
     * player_id rows of the other layers.
     *
     * @return array{inserted: int, deleted: int, kept: int, protected: int, skipped: string[]}
     */
    private function importBuildingsLayer(string $plan, int $z, array $incomingRows, array $currentRows): array
    {
        $available = [];
        $protected = 0;

        foreach ($currentRows as $row) {
            if (!empty($row['player_id'])) {
                $protected++;
                continue;
            }
            $available[$this->rowKey(self::BUILDINGS_LAYER, $row)][] = $row['id'];
        }

        $kept = 0;
        $toInsert = [];

        foreach ($incomingRows as $row) {
            self::validateIncomingRow(self::BUILDINGS_LAYER, $row);

            $key = $this->rowKey(self::BUILDINGS_LAYER, $row);

            if (!empty($available[$key])) {
                array_pop($available[$key]);
                $kept++;
            } else {
                $toInsert[] = $row;
            }
        }

        $buildings = new BuildingService();
        $skipped = [];
        $inserted = 0;

        // Removals first: moving a building from one cell to another in the
        // same push frees the old cell before the placement on the new one
        $deleted = 0;
        foreach (array_merge([], ...array_values($available)) as $entityId) {
            if ($buildings->remove((int) $entityId)) {
                $deleted++;
            }
        }

        foreach ($toInsert as $row) {
            try {
                // Editor placement: decor and elements do not block, only
                // a player's construire is held to that rule.
                $buildings->place((string) $row['name'], (object) [
                    'x'    => (int) $row['x'],
                    'y'    => (int) $row['y'],
                    'z'    => $z,
                    'plan' => $plan,
                ], overScenery: true);
                $inserted++;
            } catch (\InvalidArgumentException $e) {
                $skipped[] = $row['x'] . ',' . $row['y'] . ' ' . $row['name'] . ' — ' . $e->getMessage();
            }
        }

        return [
            'inserted'  => $inserted,
            'deleted'   => $deleted,
            'kept'      => $kept,
            'protected' => $protected,
            'skipped'   => $skipped,
        ];
    }

    /**
     * Authorable layers of a whole plan (every z), in portable form: no
     * database id, player_id rows and column excluded (same rules as the
     * version fingerprint), endTime excluded (runtime state — damages stays:
     * it encodes the author's intent, -1 = harvestable). Feeds the bundle
     * export ({@see \App\Service\ImportExport\PlanExporter}).
     *
     * @return array<string, list<array<string, mixed>>> layer => rows {x, y, z, name, …}
     */
    public function exportAllLayers(string $plan): array
    {
        $layers = [];

        foreach (self::AUTHORABLE_LAYERS as $layer => $spec) {
            /* Entity layers are read from their writer, which owns the
             * mapping between damages and the state satellite. */
            if (isset(self::ENTITY_LAYERS[$layer])) {
                $layers[$layer] = self::reconcilerFor($layer)->asPayloadRows($plan);
                continue;
            }

            $columns = 'm.name, c.x, c.y, c.z';
            $hasPlayerId = in_array('player_id', $spec['columns'], true);
            foreach ($spec['columns'] as $column) {
                if ($column !== 'player_id' && $column !== 'endTime') {
                    $columns .= ', m.`' . $column . '`';
                }
            }

            $res = $this->db->exe(
                'SELECT ' . $columns . '
                 FROM map_' . $layer . ' m
                 JOIN coords c ON c.id = m.coords_id
                 WHERE c.plan = ?' . ($hasPlayerId ? ' AND (m.player_id IS NULL OR m.player_id = 0)' : '')
                    . self::authoredRowsClause($spec) . '
                 ORDER BY c.z, c.y, c.x, m.id',
                array($plan)
            );

            $rows = [];
            while ($row = $res->fetch_assoc()) {
                $row['x'] = (int) $row['x'];
                $row['y'] = (int) $row['y'];
                $row['z'] = (int) $row['z'];
                $rows[] = $row;
            }

            $layers[$layer] = $rows;
        }

        $layers[self::BUILDINGS_LAYER] = $this->decorBuildingRows($plan);

        return $layers;
    }

    /**
     * The DECOR buildings of a whole plan, as bundle rows: what a clone
     * copies and a bundle carries. Walls are buildings since the entity
     * conversion — a bundle without them was a plan with no walls.
     *
     * @return list<array{name: string, x: int, y: int, z: int}>
     */
    public function decorBuildingRows(string $plan): array
    {
        $res = $this->db->exe(
            "SELECT p.race AS name, c.x, c.y, c.z
             FROM buildings b
             JOIN players p ON p.id = b.player_id
             JOIN coords c ON c.id = p.coords_id
             WHERE c.plan = ? AND p.owner_id IS NULL AND p.faction = '' AND b.build_state = 'built'
             ORDER BY c.z, c.y, c.x, p.id",
            array($plan)
        );

        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = ['name' => (string) $row['name'], 'x' => (int) $row['x'], 'y' => (int) $row['y'], 'z' => (int) $row['z']];
        }

        return $rows;
    }

    /**
     * Applies a bundle's buildings layer, z level by z level, with the push's
     * diff: decor missing from the rows is removed, rows missing from the
     * plan are placed, everything a player or a faction holds is untouched.
     *
     * @param list<array{name: string, x: int, y: int, z: int}> $rows
     * @return string[] placements refused, one line each
     */
    public function importDecorBuildings(string $plan, array $rows): array
    {
        $byZ = [];
        foreach ($rows as $row) {
            $byZ[(int) $row['z']][] = $row;
        }
        foreach ($this->planZLevels($plan) as $z) {
            $byZ[$z] ??= [];
        }

        $skipped = [];
        foreach ($byZ as $z => $zRows) {
            $result = $this->importBuildingsLayer($plan, $z, $zRows, $this->fetchBuildingRows($plan, $z));
            $skipped = array_merge($skipped, $result['skipped']);
        }

        return $skipped;
    }

    /** @return array<string, array> every authorable layer of the (plan, z) */
    private function fetchLayers(string $plan, int $z): array
    {
        $layers = [];

        foreach (self::AUTHORABLE_LAYERS as $layer => $spec) {
            if (isset(self::ENTITY_LAYERS[$layer])) {
                $layers[$layer] = self::reconcilerFor($layer)->asPayloadRows($plan, $z);
                continue;
            }

            $columns = 'm.id, m.name, c.x, c.y';
            foreach ($spec['columns'] as $column) {
                $columns .= ', m.`' . $column . '`';
            }

            $res = $this->db->exe(
                'SELECT ' . $columns . '
                 FROM map_' . $layer . ' m
                 JOIN coords c ON c.id = m.coords_id
                 WHERE c.plan = ? AND c.z = ?' . self::authoredRowsClause($spec) . '
                 ORDER BY c.y, c.x, m.id',
                array($plan, $z)
            );

            $rows = [];
            while ($row = $res->fetch_assoc()) {
                $row['id'] = (int) $row['id'];
                $row['x'] = (int) $row['x'];
                $row['y'] = (int) $row['y'];
                $rows[] = $row;
            }

            $layers[$layer] = $rows;
        }

        $layers[self::BUILDINGS_LAYER] = $this->fetchBuildingRows($plan, $z);

        return $layers;
    }

    /**
     * Building entities of the (plan, z), shaped as layer rows: name = the
     * type (players.race). Authorable DECOR has player_id = 0; everything
     * else (owner, faction, building site, ruin) carries a non-zero
     * player_id — same convention as player-built rows: out of the diff, out
     * of the version fingerprint, locked "(joueurs)" layer on the extension
     * side.
     *
     * @return list<array{id: int, name: string, x: int, y: int, player_id: int}>
     */
    private function fetchBuildingRows(string $plan, int $z): array
    {
        $res = $this->db->exe(
            "SELECT p.id, p.race AS name, c.x, c.y, p.owner_id, p.faction, b.build_state
             FROM buildings b
             JOIN players p ON p.id = b.player_id
             JOIN coords c ON c.id = p.coords_id
             WHERE c.plan = ? AND c.z = ?
             ORDER BY c.y, c.x, p.id",
            array($plan, $z)
        );

        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $isDecor = $row['owner_id'] === null
                && (string) $row['faction'] === ''
                && (string) $row['build_state'] === 'built';

            $rows[] = [
                'id'        => (int) $row['id'],
                'name'      => (string) $row['name'],
                'x'         => (int) $row['x'],
                'y'         => (int) $row['y'],
                'player_id' => $isDecor ? 0 : (int) ($row['owner_id'] ?? -1),
            ];
        }

        return $rows;
    }

    /** @return int[] the plan's existing z levels, ascending */
    private function planZLevels(string $plan): array
    {
        $res = $this->db->exe('SELECT DISTINCT z FROM coords WHERE plan = ? ORDER BY z', array($plan));

        $zLevels = [];
        while ($row = $res->fetch_assoc()) {
            $zLevels[] = (int) $row['z'];
        }

        return $zLevels;
    }

    /** @return list<string> existing item names, for the validator (one query instead of one per biome) */
    private function knownItemNames(): array
    {
        $res = $this->db->exe('SELECT name FROM items');

        $names = [];
        while ($row = $res->fetch_assoc()) {
            $names[] = $row['name'];
        }

        return $names;
    }

    /** @return array<string, int> "x|y" => coords_id of the (plan, z) */
    private function loadCoordsIds(string $plan, int $z): array
    {
        $res = $this->db->exe('SELECT id, x, y FROM coords WHERE plan = ? AND z = ?', array($plan, $z));

        $coordsIds = [];
        while ($row = $res->fetch_assoc()) {
            $coordsIds[$row['x'] . '|' . $row['y']] = (int) $row['id'];
        }

        return $coordsIds;
    }

    /**
     * Fingerprint of the authored content. Excludes protected rows
     * (player_id) and runtime columns (damages, endTime), which change during
     * play without being an editing conflict.
     */
    private function computeVersion(array $layers): string
    {
        $parts = [];

        foreach ($layers as $layer => $rows) {
            foreach ($rows as $row) {
                if (!empty($row['player_id'])) {
                    continue;
                }
                $parts[] = $layer . '|' . $this->rowKey($layer, $row);
            }
        }

        sort($parts);

        return sha1(implode("\n", $parts));
    }

    private function rowKey(string $layer, array $row): string
    {
        $key = $row['x'] . '|' . $row['y'] . '|' . $row['name'];

        if (self::AUTHORABLE_LAYERS[$layer]['paramsInKey'] ?? false) {
            $key .= '|' . (string) ($row['params'] ?? '');
        }
        // The same element turned the other way is another placement
        if (self::hasRotation($layer)) {
            $key .= '|' . (int) ($row['rotation'] ?? 0);
        }

        return $key;
    }

    /**
     * Turn each row flagged `composite` into the pieces of its figure.
     *
     * Tiled used to explode a composite tile itself, so the object died at
     * the door: the server only ever saw loose pieces. It now sends the
     * object, and the cut-out catalogue lays it out here.
     *
     * Rows without the flag pass through untouched, so a plugin that still
     * explodes keeps working — the two shapes coexist while animators update.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function spreadComposites(array $rows): array
    {
        $footprints = null;
        $spread = [];

        foreach ($rows as $row) {
            if (empty($row['composite']) || !isset($row['name'])) {
                unset($row['composite']);
                $spread[] = $row;
                continue;
            }

            $footprints ??= (new \App\Service\Map\EntityTypeFootprintService())->catalogue();

            [$family, $piece] = \App\Service\Map\SceneryFootprintDeriver::splitPiece((string) $row['name']);
            $footprint = $footprints[$family] ?? null;

            if ($footprint === null) {
                /* No known cut-out: nothing to lay out, and nothing guessed. */
                unset($row['composite']);
                $spread[] = $row;
                continue;
            }

            $objects = new \App\Service\Map\SceneryObjectService();

            foreach ($objects->cellsToPlace((string) $row['name'], (int) $row['x'], (int) $row['y']) as $pieceName => [$px, $py]) {
                $spread[] = ['x' => $px, 'y' => $py, 'name' => $pieceName];
            }
        }

        return $spread;
    }

    /**
     * @param list<array<string, mixed>>  $incomingRows
     * @param list<array<string, mixed>>  $currentRows
     * @param array<string, int>          $coordsIds "x|y" => id cache, complete at this point
     * @return array{inserted: int, deleted: int, kept: int, protected: int}
     */
    private function importLayer(string $layer, array $incomingRows, array $currentRows, array $coordsIds): array
    {
        // Existing rows available for matching, by key
        $available = [];
        $protected = 0;

        foreach ($currentRows as $row) {
            if (!empty($row['player_id'])) {
                $protected++;
                continue;
            }
            $available[$this->rowKey($layer, $row)][] = $row['id'];
        }

        $kept = 0;
        $toInsert = [];
        $seen = [];

        foreach ($incomingRows as $row) {
            self::validateIncomingRow($layer, $row);

            $key = $this->rowKey($layer, $row);

            /* The same thing twice on the same cell is once. A paste over
             * its own area sends two, and map_elements — whose primary key is
             * (name, coords_id) — refuses the second one, push and all. */
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            if (!empty($available[$key])) {
                array_pop($available[$key]);
                $kept++;
            } else {
                $toInsert[] = $row;
            }
        }

        $toDelete = array_merge([], ...array_values($available));

        /* Deletions first: the same element re-placed turned the other
         * way lands on the same (name, coords_id) key as the row it
         * replaces. Batched: past 65 535 parameters MySQL refuses to prepare
         * the statement, and a large plan erased at once gets there. */
        foreach (array_chunk($toDelete, self::INSERT_BATCH) as $chunk) {
            $this->db->exe(
                'DELETE FROM map_' . $layer . ' WHERE id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')',
                $chunk
            );
        }

        $this->insertRows($layer, $toInsert, $coordsIds);

        return [
            'inserted'  => count($toInsert),
            'deleted'   => count($toDelete),
            'kept'      => $kept,
            'protected' => $protected,
        ];
    }

    /**
     * Accepts payloads from before the map_walls → map_resources rename:
     * maps pulled and bundles exported back then carry the "walls" key.
     * Shared with the bundle import (PlanImporter).
     *
     * @param array<string, mixed> $layers
     * @return array<string, mixed>
     */
    public static function normalizeLegacyLayerKeys(array $layers): array
    {
        if (isset($layers['walls']) && !isset($layers['resources'])) {
            $layers['resources'] = $layers['walls'];
            unset($layers['walls']);
        }

        return $layers;
    }

    /**
     * The single checkpoint for an authored row's validity — shared between
     * the Tiled push and the bundle import (PlanImporter).
     *
     * @throws RuntimeException code 400
     */
    public static function validateIncomingRow(string $layer, mixed $row): void
    {
        if (!is_array($row)
            || !isset($row['x'], $row['y'], $row['name'])
            || !is_numeric($row['x']) || !is_numeric($row['y'])
            || !is_string($row['name'])
            || !preg_match(TileCatalogService::ASSET_NAME_PATTERN, $row['name'])
        ) {
            throw new RuntimeException('Ligne invalide dans la couche ' . $layer . ' : ' . json_encode($row), 400);
        }

        if (isset($row['params']) && (!is_scalar($row['params']) || strlen((string) $row['params']) > 255)) {
            throw new RuntimeException('Params invalide dans la couche ' . $layer . ' en ' . $row['x'] . ',' . $row['y'], 400);
        }

        if (isset($row['rotation']) && !in_array((int) $row['rotation'], self::ROTATIONS, true)) {
            throw new RuntimeException('Rotation invalide (0, 90, 180 ou 270) dans la couche ' . $layer . ' en ' . $row['x'] . ',' . $row['y'], 400);
        }
    }

    /**
     * Creates in one go the cells the push names that do not exist yet.
     *
     * One at a time ({@see View::get_coords_id}) is two or three queries per
     * cell, and a paste of a few thousand cells blows PHP's time limit. The
     * upsert keeps it idempotent — two pushes discovering the same cell
     * create it once, the unique key (plan, z, x, y) decides.
     *
     * @param array<string, mixed> $layers layers as pushed
     * @param array<string, int>   $coordsIds "x|y" => id cache, reloaded when cells are born
     */
    private function ensureCoords(string $plan, int $z, array $layers, array &$coordsIds): void
    {
        $missing = [];

        foreach ($layers as $rows) {
            if (!is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                if (!is_array($row) || !isset($row['x'], $row['y']) || !is_numeric($row['x']) || !is_numeric($row['y'])) {
                    continue;
                }

                $key = (int) $row['x'] . '|' . (int) $row['y'];

                if (!isset($coordsIds[$key])) {
                    $missing[$key] = [(int) $row['x'], (int) $row['y']];
                }
            }
        }

        if ($missing === []) {
            return;
        }

        foreach (array_chunk(array_values($missing), self::INSERT_BATCH) as $chunk) {
            $params = [];

            foreach ($chunk as [$x, $y]) {
                array_push($params, $x, $y, $z, $plan);
            }

            $this->db->exe(
                'INSERT INTO coords (x, y, z, plan) VALUES '
                    . implode(', ', array_fill(0, count($chunk), '(?, ?, ?, ?)'))
                    . ' ON DUPLICATE KEY UPDATE id = id',
                $params
            );
        }

        $coordsIds = $this->loadCoordsIds($plan, $z);
    }

    /**
     * Inserts a layer's rows in batches.
     *
     * Uniform columns: `name` and the cell, plus `params` and `rotation` for
     * the layers that carry them ('' and 0 by default). The rest —
     * foreground, endTime — takes the schema default.
     *
     * @param list<array<string, mixed>> $rows
     * @param array<string, int>         $coordsIds "x|y" => id cache, complete at this point
     */
    private function insertRows(string $layer, array $rows, array $coordsIds): void
    {
        if ($rows === []) {
            return;
        }

        $withParams = self::AUTHORABLE_LAYERS[$layer]['paramsInKey'];
        $withRotation = self::hasRotation($layer);
        $columns = '(`name`, coords_id' . ($withParams ? ', `params`' : '') . ($withRotation ? ', `rotation`' : '') . ')';
        $placeholder = '(' . implode(', ', array_fill(0, 2 + (int) $withParams + (int) $withRotation, '?')) . ')';

        foreach (array_chunk($rows, self::INSERT_BATCH) as $chunk) {
            $params = [];

            foreach ($chunk as $row) {
                $coordsKey = (int) $row['x'] . '|' . (int) $row['y'];

                if (!isset($coordsIds[$coordsKey])) {
                    throw new RuntimeException(
                        'Création de coordonnées impossible en ' . $row['x'] . ',' . $row['y'],
                        500
                    );
                }

                $params[] = (string) $row['name'];
                $params[] = $coordsIds[$coordsKey];

                if ($withParams) {
                    $params[] = (string) ($row['params'] ?? '');
                }
                if ($withRotation) {
                    $params[] = (int) ($row['rotation'] ?? 0);
                }
            }

            $this->db->exe(
                'INSERT INTO map_' . $layer . ' ' . $columns . ' VALUES '
                    . implode(', ', array_fill(0, count($chunk), $placeholder)),
                $params
            );
        }
    }
}
