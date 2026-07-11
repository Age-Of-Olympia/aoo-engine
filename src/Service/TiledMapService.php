<?php

namespace App\Service;

use Classes\Db;
use Classes\View;
use RuntimeException;

/**
 * Export / import des plans du jeu pour l'extension Tiled (tools/tiled/aoo).
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
     *    l'état runtime préservé sur les lignes conservées.
     */
    public const AUTHORABLE_LAYERS = [
        'tiles'       => ['columns' => ['foreground', 'player_id'], 'paramsInKey' => false],
        'routes'      => ['columns' => ['player_id'],               'paramsInKey' => false],
        'plants'      => ['columns' => ['params'],                  'paramsInKey' => true],
        'walls'       => ['columns' => ['damages', 'player_id'],    'paramsInKey' => false],
        'elements'    => ['columns' => ['endTime'],                 'paramsInKey' => false],
        'foregrounds' => ['columns' => [],                          'paramsInKey' => false],
        'triggers'    => ['columns' => ['params'],                  'paramsInKey' => true],
        'dialogs'     => ['columns' => ['params'],                  'paramsInKey' => true],
    ];

    private const IMAGE_EXTENSIONS = ['png', 'webp', 'gif'];

    public const TILE_SIZE = 50;

    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
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

        $layers = $this->fetchLayers($plan, $z);
        ['catalog' => $catalog, 'images' => $images] = $this->buildCatalog();

        return [
            'plan'       => $plan,
            'z'          => $z,
            'zLevels'    => $zLevels,
            'tileSize'   => self::TILE_SIZE,
            'version'    => $this->computeVersion($layers),
            'layers'     => $layers,
            'catalog'    => $catalog,
            'images'     => $images,
            'composites' => $this->buildComposites(),
            'planConfig' => $this->readPlanConfig($plan),
        ];
    }

    /**
     * Configuration de plan éditable depuis Tiled : le fond/ambiance (clé
     * « bg » du JSON de plan, cf. Classes/View.php) et les images
     * candidates — les grandes images de img/tiles (fonds, météo), justement
     * celles écartées du catalogue de tuiles posables.
     *
     * @return array{bg: ?string, bgChoices: string[]}
     */
    private function readPlanConfig(string $plan): array
    {
        $json = @json_decode((string) @file_get_contents($this->planJsonPath($plan)), true);

        $choices = [];
        $dir = $_SERVER['DOCUMENT_ROOT'] . '/img/tiles';
        foreach (is_dir($dir) ? scandir($dir) : [] as $fileName) {
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($ext, self::IMAGE_EXTENSIONS, true)) {
                continue;
            }
            $size = @getimagesize($dir . '/' . $fileName);
            if ($size && ($size[0] > self::TILE_SIZE * 1.2 || $size[1] > self::TILE_SIZE * 1.2)) {
                $choices[] = 'img/tiles/' . $fileName;
            }
        }
        sort($choices);

        return [
            'bg'        => is_array($json) ? ($json['bg'] ?? null) : null,
            'bgChoices' => $choices,
        ];
    }

    /**
     * Écrit la clé « bg » du JSON de plan ('' = retirer, retour au fond par
     * défaut img/tiles/<plan>.webp). Crée le JSON minimal si absent.
     *
     * @throws RuntimeException code 400 si la valeur n'est pas une image connue
     */
    public function writePlanBg(string $plan, string $bg): void
    {
        if ($bg !== ''
            && (!preg_match('#^img/tiles/[a-zA-Z0-9_.-]+$#', $bg)
                || !file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $bg))
        ) {
            throw new RuntimeException('Fond de plan invalide : ' . $bg, 400);
        }

        $path = $this->planJsonPath($plan);
        $json = @json_decode((string) @file_get_contents($path), true);
        if (!is_array($json)) {
            $json = ['name' => $plan];
        }

        if ($bg === '') {
            unset($json['bg']);
        } else {
            $json['bg'] = $bg;
        }

        if (!is_dir(dirname($path))) {
            throw new RuntimeException('Répertoire des plans introuvable : ' . dirname($path), 500);
        }

        file_put_contents($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    }

    private function planJsonPath(string $plan): string
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/datas/private/plans/' . $plan . '.json';
    }

    /**
     * Structures multi-tuiles : les grandes images découpées en morceaux
     * « base-NN » (row-major depuis le coin haut-gauche, cf. les convert.sh
     * historiques). Convention détectée : img/<couche>/<base>/<base>.png
     * (l'originale entière) + les morceaux img/<couche>/<base>-NN.png à la
     * racine. L'extension en fait des tuiles de palette « une structure en
     * un clic » que le push ré-éclate en morceaux individuels.
     *
     * @return array<string, array<int, array{name: string, image: string, width: int, height: int, pieces: string[]}>>
     */
    private function buildComposites(): array
    {
        $composites = [];

        foreach (array_keys(self::AUTHORABLE_LAYERS) as $layer) {
            // Le sol reste strictement 50x50 : les structures multi-tuiles
            // appartiennent aux couches de décor (foregrounds, elements…)
            if ($layer === 'tiles') {
                continue;
            }
            $dir = $_SERVER['DOCUMENT_ROOT'] . '/img/' . $layer;

            foreach (is_dir($dir) ? scandir($dir) : [] as $base) {
                if ($base === '.' || $base === '..'
                    || !preg_match('/^[a-zA-Z0-9_.-]+$/', $base)
                    || !is_file($dir . '/' . $base . '/' . $base . '.png')
                ) {
                    continue;
                }

                $size = @getimagesize($dir . '/' . $base . '/' . $base . '.png');
                if (!$size || $size[0] % self::TILE_SIZE !== 0 || $size[1] % self::TILE_SIZE !== 0) {
                    continue;
                }

                $width = (int) ($size[0] / self::TILE_SIZE);
                $height = (int) ($size[1] / self::TILE_SIZE);
                if ($width * $height < 2) {
                    continue;
                }

                // Tous les morceaux doivent exister à la racine (rendu du jeu)
                $pieces = [];
                for ($i = 0; $i < $width * $height; $i++) {
                    $piece = sprintf('%s-%02d', $base, $i);
                    if (!file_exists($dir . '/' . $piece . '.png')) {
                        continue 2;
                    }
                    $pieces[] = $piece;
                }

                $composites[$layer][] = [
                    'name'   => $base,
                    'image'  => 'img/' . $layer . '/' . $base . '/' . $base . '.png',
                    'width'  => $width,
                    'height' => $height,
                    'pieces' => $pieces,
                ];
            }
        }

        return $composites;
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
            || !preg_match('/^[a-zA-Z0-9_.-]+$/', $row['name'])
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
            $values['damages'] = (defined('WALLS_PV') && (WALLS_PV[$row['name']] ?? 0) === -1) ? -1 : 0;
        }

        if (self::AUTHORABLE_LAYERS[$layer]['paramsInKey'] && isset($row['params']) && $row['params'] !== '') {
            $values['params'] = (string) $row['params'];
        }

        $this->db->insert('map_' . $layer, $values);
    }

    /**
     * Catalogue complet des images disponibles par couche (pas seulement
     * celles déjà posées) : palette entière dans l'éditeur, indispensable
     * pour les plans neufs. `images` couvre toute tuile référençable —
     * une tuile posée sans image sur disque est simplement absente de la
     * table, ce que l'extension traite comme « pas d'image ».
     *
     * @return array{catalog: array<string, string[]>, images: array<string, string>}
     */
    private function buildCatalog(): array
    {
        $catalog = [];
        $images = [];

        foreach (array_keys(self::AUTHORABLE_LAYERS) as $layer) {
            $dir = $_SERVER['DOCUMENT_ROOT'] . '/img/' . $layer;
            $names = [];

            foreach (is_dir($dir) ? scandir($dir) : [] as $fileName) {
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $name = pathinfo($fileName, PATHINFO_FILENAME);

                if (!in_array($ext, self::IMAGE_EXTENSIONS, true)
                    || !preg_match('/^[a-zA-Z0-9_.-]+$/', $name)
                    || isset($names[$name])
                ) {
                    continue;
                }

                // Écarte les images qui ne sont pas des tuiles posables :
                // fonds de plan et effets météo (gaia.webp 500x500,
                // dust_storm.webp 2848x862…) rangés dans les mêmes dossiers
                $size = @getimagesize($dir . '/' . $fileName);
                if (!$size || $size[0] > self::TILE_SIZE * 1.2 || $size[1] > self::TILE_SIZE * 1.2) {
                    continue;
                }

                $names[$name] = true;
                $images[$layer . '/' . $name] = 'img/' . $layer . '/' . $fileName;
            }

            $catalog[$layer] = array_keys($names);
            sort($catalog[$layer]);
        }

        return ['catalog' => $catalog, 'images' => $images];
    }
}
