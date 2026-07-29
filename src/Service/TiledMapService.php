<?php

namespace App\Service;

use Classes\Db;
use Classes\View;
use RuntimeException;

/**
 * Export / import des plans du jeu pour l'extension Tiled (tools/tiled/aoo).
 *
 * Ce service porte le moteur de diff transactionnel sur les tables map_* et
 * compose deux services voisins : TileCatalogService (images de img/) et
 * PlanConfigService (JSON de plan).
 *
 * Un « plan » exporté = les couches authorables d'un (plan, z) donné.
 * map_items n'apparaît jamais : c'est de l'état runtime (objets au sol).
 *
 * L'import est un diff par couche, clé d'identité (x, y, name[, params]) :
 *  - les lignes identiques sont conservées telles quelles (leurs colonnes
 *    runtime — damages des murs, endTime des éléments — survivent) ;
 *  - les lignes construites par des joueurs (player_id non nul) sont
 *    intouchables : jamais supprimées, ignorées par le diff ;
 *  - le tout est transactionnel, avec contrôle de version optimiste :
 *    la version calculée au pull doit correspondre à l'état courant,
 *    sinon 409 (un autre admin — ou le jeu — a modifié le plan).
 *
 * La couche « buildings » est particulière : elle n'a pas de table map_*,
 * ses lignes sont les ENTITÉS bâtiment du niveau (players type building).
 * À l'export, une tuile par entité (name = le type / la race structure) ;
 * à l'import, le même diff (x, y, name), mais chaque pose passe par
 * BuildingService::place() et chaque retrait par remove() — hors de la
 * transaction map_* (autre connexion), après elle, pose par pose : une
 * case occupée est signalée (« skipped ») sans condamner le push. Seul le
 * DÉCOR est diffable (sans propriétaire ni faction, état built) ; le
 * reste — bâtiments de joueurs, avant-postes de faction, chantiers,
 * ruines — est protégé comme les lignes player_id des autres couches.
 */
class TiledMapService
{
    /** Règle unique des noms de plan (endpoints : tiledValidPlanName()) */
    public const PLAN_NAME_PATTERN = '/^[a-z0-9_-]{1,64}$/';

    /**
     * Spécification des couches authorables.
     *  - columns : colonnes exportées en plus de name/x/y ;
     *  - paramsInKey : params fait partie de la clé d'identité (contenu
     *    authoré — modifier le params d'un trigger = suppression +
     *    insertion). Pour les autres couches, les colonnes hors clé sont de
     *    l'état runtime préservé sur les lignes conservées ;
     *  - composites : la couche accepte les structures multi-tuiles (le sol
     *    reste strictement 50x50).
     */
    public const AUTHORABLE_LAYERS = [
        'tiles'       => ['columns' => ['foreground', 'player_id'], 'paramsInKey' => false, 'composites' => false],
        'routes'      => ['columns' => ['player_id'],               'paramsInKey' => false, 'composites' => true],
        'plants'      => ['columns' => ['params'],                  'paramsInKey' => true,  'composites' => true],
        'resources'   => ['columns' => ['damages', 'player_id'],    'paramsInKey' => false, 'composites' => true],
        'elements'    => ['columns' => ['endTime'],                 'paramsInKey' => false, 'composites' => true],
        'foregrounds' => ['columns' => [],                          'paramsInKey' => false, 'composites' => true],
        'triggers'    => ['columns' => ['params'],                  'paramsInKey' => true,  'composites' => false],
        'dialogs'     => ['columns' => ['params'],                  'paramsInKey' => true,  'composites' => false],
    ];

    /** Couche virtuelle des entités bâtiment (pas de table map_*) */
    public const BUILDINGS_LAYER = 'buildings';

    /** The layer whose rows may name a whole object rather than a piece. */
    public const SCENERY_LAYER = 'foregrounds';

    /**
     * Répertoire d'images d'une couche. La couche « resources »
     * (table map_resources, ex-map_walls) garde img/walls : le dépôt
     * d'assets n'est pas versionné ici et les avatars des entités
     * converties pointent des chemins img/walls/… copiés en base —
     * renommer le dossier casserait les deux.
     */
    public static function layerImageDir(string $layer): string
    {
        return $layer === 'resources' ? 'walls' : $layer;
    }

    public const TILE_SIZE = 50;

    private Db $db;
    private TileCatalogService $catalog;
    private PlanConfigService $planConfig;

    public function __construct()
    {
        $this->db = new Db();
        $this->catalog = new TileCatalogService();
        $this->planConfig = new PlanConfigService();
    }

    /** @return array|null null si le (plan, z) n'existe pas */
    public function exportPlan(string $plan, int $z): ?array
    {
        $zLevels = $this->planZLevels($plan);

        // Un niveau vide mais existant (coords sans contenu) reste pullable :
        // l'extension multi-z doit pouvoir l'afficher et le remplir
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

        // Depuis la conversion des obstacles en entités bâtiment, la palette
        // resources ne propose que ce qui reste posable en map_resources sur
        // ce plan (ressources, autels, unique_* — tout sur les plans de
        // tutoriel). Les murs déjà posés restent visibles : buildLevel les
        // tient des lignes du plan, pas du catalogue.
        $catalog['resources'] = ResourcePaletteService::filterNames($catalog['resources'] ?? [], $plan);
        $composites['resources'] = array_values(array_filter(
            $composites['resources'] ?? [],
            fn(array $composite) => ResourcePaletteService::isAuthorable($composite['name'], $plan)
        ));

        // Palette bâtiments : le catalogue des types de structure (mêmes
        // entrées que admin → Bâtiments), sprite résolu comme au rendu
        $catalog[self::BUILDINGS_LAYER] = [];
        foreach ((new RaceService())->getRacesByKind(\App\Enum\EntityCategory::Structure->value) as $race) {
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
            'planConfig' => [
                'values' => $this->planConfig->read($plan),
            ],
            'zConfig'    => $this->planConfig->readZLevel($plan, $z),
        ];
    }

    /**
     * Cas d'usage complet d'un push : valide la configuration de plan AVANT
     * la transaction (aucun 400 possible après le commit des couches),
     * importe les couches, écrit la configuration, recale les bornes du
     * niveau, et remonte le bilan de santé du JSON de plan.
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

        $this->planConfig->writeZLevel($plan, $z, $zConfig ?? [], $this->levelBounds($plan, $z));

        $health = $this->planConfig->validate($plan, $this->db, $this->knownItemNames());
        if ($health['errors'] !== [] || $health['warnings'] !== []) {
            $result['planHealth'] = $health;
        }

        return $result;
    }

    /**
     * Données de disposition pour un monde Tiled : par plan, sa position
     * (x, y) sur la carte du monde, son étendue de contenu par niveau z, et
     * les plans vers lesquels ses déclencheurs `tp` mènent (graphe de liens,
     * pour placer les plans hors grille et signaler les liens cassés).
     *
     * Les plans dont le nom ne respecte pas PLAN_NAME_PATTERN (résidus
     * historiques de la table coords) sont écartés — export.php les
     * refuserait — et listés dans `ignored` pour être signalés à l'admin.
     *
     * @return array{tileSize: int, plans: array<string, array<string, mixed>>, ignored: string[]}
     */
    public function worldLayout(): array
    {
        $plans = [];
        $ignored = [];

        // Étendue du contenu par (plan, z)
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

        // Liens tp : plan source → plans destination distincts
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

        // Position (x, y) depuis le JSON de plan + liens
        foreach ($plans as $plan => &$data) {
            $position = $this->planConfig->readPosition($plan);
            $data['x'] = $position['x'];
            $data['y'] = $position['y'];
            $data['links'] = array_keys($links[$plan] ?? []);
        }

        return ['tileSize' => self::TILE_SIZE, 'plans' => $plans, 'ignored' => array_keys($ignored)];
    }

    /**
     * Plans existants — mêmes résidus écartés que worldLayout(), un plan
     * listé ici doit toujours être pullable via export.php.
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

    /** @throws RuntimeException code 409 si le plan existe déjà */
    public function createPlan(string $plan): void
    {
        if ($this->planZLevels($plan) !== []) {
            throw new RuntimeException('Le plan existe déjà : ' . $plan, 409);
        }

        // Une coordonnée d'amorce suffit : le plan existe, l'import créera
        // les autres coords au fil des éditions
        $coordsId = View::get_coords_id((object) ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => $plan]);

        if (!$coordsId) {
            throw new RuntimeException('Création du plan impossible : ' . $plan, 500);
        }
    }

    /**
     * @param array<string, array> $incomingLayers couches envoyées par l'extension
     * @return array{layers: array, newVersion: string}
     * @throws RuntimeException code 400 (payload invalide) ou 409 (conflit de version)
     */
    public function importPlan(string $plan, int $z, array $incomingLayers, string $expectedVersion): array
    {
        $incomingLayers = self::normalizeLegacyLayerKeys($incomingLayers);

        foreach (array_keys($incomingLayers) as $layer) {
            if (!isset(self::AUTHORABLE_LAYERS[$layer]) && $layer !== self::BUILDINGS_LAYER) {
                throw new RuntimeException('Couche inconnue : ' . $layer, 400);
            }
        }

        // Les bâtiments sont des entités : posés/retirés via BuildingService
        // (autre connexion), APRÈS la transaction map_* — un mur supprimé
        // dans le même push libère sa case avant la pose
        $incomingBuildings = null;
        if (array_key_exists(self::BUILDINGS_LAYER, $incomingLayers)) {
            $incomingBuildings = $incomingLayers[self::BUILDINGS_LAYER];
            unset($incomingLayers[self::BUILDINGS_LAYER]);
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

        $coordsIds = $this->loadCoordsIds($plan, $z);
        $report = [];

        $this->db->beginTransaction();
        try {
            foreach ($incomingLayers as $layer => $rows) {
                $report[$layer] = $this->importLayer($plan, $z, $layer, $rows, $currentLayers[$layer], $coordsIds);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        // L'état post-import est connu sans relire la base : les couches
        // importées valent exactement les lignes reçues (les lignes joueurs,
        // hors diff, sont aussi hors empreinte), les autres n'ont pas bougé
        $postLayers = array_merge($currentLayers, $incomingLayers);

        /* Scenery laid down by a push must become an entity, or its cut-out's
         * roles are read by nobody. Derived from what is on the map rather
         * than from what the push said, so it also catches what earlier
         * pushes left behind. */
        if (isset($incomingLayers[self::SCENERY_LAYER])) {
            $report[self::SCENERY_LAYER]['entities'] =
                (new \App\Service\Map\SceneryObjectService())->convertOrphans();
        }

        if ($incomingBuildings !== null) {
            $report[self::BUILDINGS_LAYER] = $this->importBuildingsLayer(
                $plan,
                $z,
                $incomingBuildings,
                $currentLayers[self::BUILDINGS_LAYER]
            );
            // Contrairement aux map_*, l'état final peut différer des lignes
            // reçues (poses refusées) : relire les entités réelles
            $postLayers[self::BUILDINGS_LAYER] = $this->fetchBuildingRows($plan, $z);
        }

        return [
            'layers'     => $report,
            'newVersion' => $this->computeVersion($postLayers),
        ];
    }

    /**
     * Diff de la couche bâtiments : même clé d'identité (x, y, type) que les
     * couches de tuiles, mais chaque pose est un BuildingService::place()
     * (validations d'occupation comprises) et chaque retrait un remove().
     * Une pose refusée est signalée dans `skipped` sans faire échouer le
     * push ; les entités protégées (propriétaire, faction, chantier, ruine)
     * sont hors diff comme les lignes player_id des autres couches.
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

        // Retraits d'abord : déplacer un bâtiment d'une case à l'autre dans
        // le même push libère l'ancienne case avant la pose sur la nouvelle
        $deleted = 0;
        foreach (array_merge([], ...array_values($available)) as $entityId) {
            if ($buildings->remove((int) $entityId)) {
                $deleted++;
            }
        }

        foreach ($toInsert as $row) {
            try {
                $buildings->place((string) $row['name'], (object) [
                    'x'    => (int) $row['x'],
                    'y'    => (int) $row['y'],
                    'z'    => $z,
                    'plan' => $plan,
                ]);
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
     * Couches authorables d'un plan entier (tous z), sous forme portable :
     * pas d'id de base, lignes et colonne player_id exclues (mêmes règles
     * que l'empreinte de version), endTime exclu (état runtime — damages
     * reste : il encode l'intention d'auteur, -1 = récoltable). Alimente
     * l'export de bundle ({@see \App\Service\ImportExport\PlanExporter}).
     *
     * @return array<string, list<array<string, mixed>>> couche => lignes {x, y, z, name, …}
     */
    public function exportAllLayers(string $plan): array
    {
        $layers = [];

        foreach (self::AUTHORABLE_LAYERS as $layer => $spec) {
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
                 WHERE c.plan = ?' . ($hasPlayerId ? ' AND (m.player_id IS NULL OR m.player_id = 0)' : '') . '
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

        return $layers;
    }

    /** @return array<string, array> toutes les couches authorables du (plan, z) */
    private function fetchLayers(string $plan, int $z): array
    {
        $layers = [];

        foreach (self::AUTHORABLE_LAYERS as $layer => $spec) {

            $columns = 'm.id, m.name, c.x, c.y';
            foreach ($spec['columns'] as $column) {
                $columns .= ', m.`' . $column . '`';
            }

            $res = $this->db->exe(
                'SELECT ' . $columns . '
                 FROM map_' . $layer . ' m
                 JOIN coords c ON c.id = m.coords_id
                 WHERE c.plan = ? AND c.z = ?
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
     * Entités bâtiment du (plan, z), sous la forme des lignes de couche :
     * name = le type (players.race). Le DÉCOR authorable a player_id = 0 ;
     * tout le reste (propriétaire, faction, chantier, ruine) porte un
     * player_id non nul — même convention que les lignes construites par
     * les joueurs : hors diff, hors empreinte de version, couche
     * verrouillée « (joueurs) » côté extension.
     *
     * @return list<array{id: int, name: string, x: int, y: int, player_id: int}>
     */
    private function fetchBuildingRows(string $plan, int $z): array
    {
        $res = $this->db->exe(
            "SELECT p.id, p.race AS name, c.x, c.y, b.owner_id, b.faction, b.build_state
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

    /** @return int[] niveaux z existants du plan, croissants */
    private function planZLevels(string $plan): array
    {
        $res = $this->db->exe('SELECT DISTINCT z FROM coords WHERE plan = ? ORDER BY z', array($plan));

        $zLevels = [];
        while ($row = $res->fetch_assoc()) {
            $zLevels[] = (int) $row['z'];
        }

        return $zLevels;
    }

    /** @return array{minX: int, maxX: int, minY: int, maxY: int}|null étendue du niveau */
    private function levelBounds(string $plan, int $z): ?array
    {
        $res = $this->db->exe(
            'SELECT MIN(x) minX, MAX(x) maxX, MIN(y) minY, MAX(y) maxY FROM coords WHERE plan = ? AND z = ?',
            array($plan, $z)
        );
        $bounds = $res->fetch_assoc();

        if ($bounds === null || $bounds['minX'] === null) {
            return null;
        }

        return array_map('intval', $bounds);
    }

    /** @return list<string> noms d'items existants, pour le validator (une requête au lieu d'une par biome) */
    private function knownItemNames(): array
    {
        $res = $this->db->exe('SELECT name FROM items');

        $names = [];
        while ($row = $res->fetch_assoc()) {
            $names[] = $row['name'];
        }

        return $names;
    }

    /** @return array<string, int> "x|y" => coords_id du (plan, z) */
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
     * Empreinte du contenu authoré. Exclut les lignes protégées (player_id)
     * et les colonnes runtime (damages, endTime), qui évoluent pendant le jeu
     * sans que ce soit un conflit d'édition.
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

        return $key;
    }

    /**
     * @param array<string, int> $coordsIds cache "x|y" => id, enrichi au fil des créations
     * @return array{inserted: int, deleted: int, kept: int, protected: int}
     */
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

    private function importLayer(string $plan, int $z, string $layer, array $incomingRows, array $currentRows, array &$coordsIds): array
    {
        // Lignes existantes disponibles pour le rapprochement, par clé
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

        foreach ($incomingRows as $row) {
            self::validateIncomingRow($layer, $row);

            $key = $this->rowKey($layer, $row);

            if (!empty($available[$key])) {
                array_pop($available[$key]);
                $kept++;
            } else {
                $toInsert[] = $row;
            }
        }

        foreach ($toInsert as $row) {
            $this->insertRow($plan, $z, $layer, $row, $coordsIds);
        }

        $toDelete = array_merge([], ...array_values($available));

        if ($toDelete !== []) {
            $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
            $this->db->exe('DELETE FROM map_' . $layer . ' WHERE id IN (' . $placeholders . ')', $toDelete);
        }

        return [
            'inserted'  => count($toInsert),
            'deleted'   => count($toDelete),
            'kept'      => $kept,
            'protected' => $protected,
        ];
    }

    /**
     * Accepte les payloads d'avant le renommage map_walls → map_resources :
     * les cartes pullées et les bundles exportés à l'époque portent la clé
     * « walls ». Partagé avec l'import de bundle (PlanImporter).
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
     * Point de contrôle unique de la validité d'une ligne authorée — partagé
     * entre le push Tiled et l'import de bundle (PlanImporter).
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
    }

    /** @param array<string, int> $coordsIds cache "x|y" => id, enrichi au fil des créations */
    private function insertRow(string $plan, int $z, string $layer, array $row, array &$coordsIds): void
    {
        // Les obstacles/décor sont des entités bâtiment depuis leur
        // conversion : map_resources ne reçoit plus que les ressources et les
        // survivants (autels, unique_*, plans de tutoriel). Avant toute
        // écriture : la création de coords vit hors transaction.
        if ($layer === 'resources' && !ResourcePaletteService::isAuthorable($row['name'], $plan)) {
            throw new RuntimeException(
                'Mur « ' . $row['name'] . ' » en ' . $row['x'] . ',' . $row['y']
                    . ' : les obstacles se posent sur la couche buildings (ou admin → Bâtiments) — '
                    . 'la couche resources ne reçoit que les ressources récoltables, les autels et les unique_*.',
                400
            );
        }

        $coordsKey = (int) $row['x'] . '|' . (int) $row['y'];

        if (!isset($coordsIds[$coordsKey])) {
            $coordsId = View::get_coords_id((object) [
                'x'    => (int) $row['x'],
                'y'    => (int) $row['y'],
                'z'    => $z,
                'plan' => $plan,
            ]);

            if (!$coordsId) {
                throw new RuntimeException('Création de coordonnées impossible en ' . $row['x'] . ',' . $row['y'], 500);
            }

            $coordsIds[$coordsKey] = (int) $coordsId;
        }

        $values = [
            'name'      => $row['name'],
            'coords_id' => $coordsIds[$coordsKey],
        ];

        if ($layer === 'resources') {
            // Défaut authoré : -1 (récoltable) pour les ressources du
            // catalogue resource_types, 0 (intact) pour les autres murs
            $values['damages'] = ResourceTypeService::isHarvestable($row['name']) ? -1 : 0;
        }

        if (self::AUTHORABLE_LAYERS[$layer]['paramsInKey'] && isset($row['params']) && $row['params'] !== '') {
            $values['params'] = (string) $row['params'];
        }

        $this->db->insert('map_' . $layer, $values);
    }
}
