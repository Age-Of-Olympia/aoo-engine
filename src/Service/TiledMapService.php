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
        'walls'       => ['columns' => ['damages', 'player_id'],    'paramsInKey' => false, 'composites' => true],
        'elements'    => ['columns' => ['endTime'],                 'paramsInKey' => false, 'composites' => true],
        'foregrounds' => ['columns' => [],                          'paramsInKey' => false, 'composites' => true],
        'triggers'    => ['columns' => ['params'],                  'paramsInKey' => true,  'composites' => false],
        'dialogs'     => ['columns' => ['params'],                  'paramsInKey' => true,  'composites' => false],
    ];

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

        return [
            'plan'       => $plan,
            'z'          => $z,
            'zLevels'    => $zLevels,
            'tileSize'   => self::TILE_SIZE,
            'version'    => $this->computeVersion($layers),
            'layers'     => $layers,
            'catalog'    => $catalog,
            'images'     => $images,
            'composites' => $this->catalog->buildComposites($compositeLayers),
            'planConfig' => [
                'values'    => $this->planConfig->read($plan),
                'bgChoices' => $this->catalog->backgroundChoices(),
            ],
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
    public function applyPush(string $plan, int $z, array $layers, string $expectedVersion, ?array $planConfig): array
    {
        $parsedConfig = $planConfig !== null ? $this->planConfig->parse($planConfig) : null;

        $result = $this->importPlan($plan, $z, $layers, $expectedVersion);

        if ($parsedConfig !== null) {
            $this->planConfig->write($plan, $parsedConfig);
        }

        $bounds = $this->levelBounds($plan, $z);
        if ($bounds !== null) {
            $this->planConfig->writeZLevelBounds($plan, $z, $bounds);
        }

        $health = $this->planConfig->validate($plan, $this->db, $this->knownItemNames());
        if ($health['errors'] !== [] || $health['warnings'] !== []) {
            $result['planHealth'] = $health;
        }

        return $result;
    }

    /** @return array<string, array{zLevels: int[], coords: int}> plans existants */
    public function listPlans(): array
    {
        $res = $this->db->exe('SELECT plan, z, COUNT(*) AS n FROM coords GROUP BY plan, z ORDER BY plan, z');

        $plans = [];
        while ($row = $res->fetch_assoc()) {
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
        // WALLS_PV (damages par défaut des murs) : dépendance du service,
        // pas de ses appelants
        require_once __DIR__ . '/../../config/constants.php';

        foreach (array_keys($incomingLayers) as $layer) {
            if (!isset(self::AUTHORABLE_LAYERS[$layer])) {
                throw new RuntimeException('Couche inconnue : ' . $layer, 400);
            }
        }

        $currentLayers = $this->fetchLayers($plan, $z);

        if (!hash_equals($this->computeVersion($currentLayers), $expectedVersion)) {
            throw new RuntimeException(
                'Le plan a changé depuis le pull — refaire un pull avant de pousser.',
                409
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

        return [
            'layers'     => $report,
            'newVersion' => $this->computeVersion($postLayers),
        ];
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

        return $layers;
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

        if (self::AUTHORABLE_LAYERS[$layer]['paramsInKey']) {
            $key .= '|' . (string) ($row['params'] ?? '');
        }

        return $key;
    }

    /**
     * @param array<string, int> $coordsIds cache "x|y" => id, enrichi au fil des créations
     * @return array{inserted: int, deleted: int, kept: int, protected: int}
     */
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
            $this->validateIncomingRow($layer, $row);

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

    private function validateIncomingRow(string $layer, mixed $row): void
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

        if ($layer === 'walls') {
            // Défaut authoré : -1 (récoltable) pour les ressources de
            // WALLS_PV, 0 (intact) pour les autres murs
            $values['damages'] = ((WALLS_PV[$row['name']] ?? 0) === -1) ? -1 : 0;
        }

        if (self::AUTHORABLE_LAYERS[$layer]['paramsInKey'] && isset($row['params']) && $row['params'] !== '') {
            $values['params'] = (string) $row['params'];
        }

        $this->db->insert('map_' . $layer, $values);
    }
}
