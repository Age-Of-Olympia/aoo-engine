<?php

namespace App\Service;

use Classes\Db;
use GdImage;
use RuntimeException;

/**
 * Tuiles de transition entre biomes pour l'autotiling Tiled (pinceau
 * Terrain) : analyse d'un plan, génération des fondus PNG et déclaration des
 * wangId dans tools/tiled/aoo/terrains.json.
 *
 * Principe : sur une grille de tuiles pleines, chaque point de coin
 * (intersection de 4 cases) où 2 à 4 biomes se rencontrent exige que toutes
 * les tuiles de transition de cet ensemble existent — sinon le pinceau pose
 * la tuile la plus proche par wangId, qui peut appartenir à une autre paire
 * (« morceaux d'autres terrains »). Une tuile de transition est le fondu
 * bilinéaire des images des biomes affectés à ses 4 coins (TL,TR,BR,BL) :
 * 14 affectations pour une paire, 36 pour un trio, 24 pour un quatuor.
 *
 * Deux consommateurs : l'outil CLI tools/tiled/generate_transitions.php
 * (cartes .tmj pullées) et le panneau admin des cartes locales (plan vivant
 * en base). Les PNG produits (img/<couche>/trans_*.png) sont rendus par le
 * jeu comme n'importe quelle tuile ; leur couleur carte est mélangée par
 * ColorService::colorFor à partir du nom.
 */
class TerrainTransitionService
{
    /** Couche sol : la seule où l'autotiling de biomes a un sens en jeu. */
    public const GROUND_LAYER = 'tiles';

    private const CORNER_COUNT = 4;

    /** Positions des coins TL,TR,BR,BL dans un wangId Tiled
     *  ([haut, TR, droite, BR, bas, BL, gauche, TL]). */
    private const WANG_POSITIONS = [7, 1, 3, 5];

    private string $root;
    private ?Db $db;

    /** @var array<string, GdImage> cache des images de biome chargées */
    private array $tileImages = [];

    /** @var list<list<list<float>>>|null poids bilinéaires des 4 coins */
    private ?array $cornerWeights = null;

    public function __construct(?Db $db = null, ?string $root = null)
    {
        $this->db = $db;
        $this->root = $root ?? (($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2));
    }

    /* ------------------------------------------------------------------ */
    /* terrains.json                                                       */
    /* ------------------------------------------------------------------ */

    public function terrainsPath(): string
    {
        return $this->root . '/tools/tiled/aoo/terrains.json';
    }

    /** @return array<string, mixed> */
    public function loadTerrains(): array
    {
        $raw = @file_get_contents($this->terrainsPath());
        $terrains = $raw === false ? [] : json_decode($raw, true);
        if (!is_array($terrains)) {
            throw new RuntimeException('terrains.json illisible : ' . $this->terrainsPath());
        }
        return $terrains;
    }

    /** @param array<string, mixed> $terrains */
    public function saveTerrains(array $terrains): void
    {
        // Sur un serveur déployé, tools/ n'est pas copié (deploy_code.sh) :
        // terrains.json y est un état runtime, créé à la première écriture
        // (classification ou génération depuis le panneau admin).
        $dir = dirname($this->terrainsPath());
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Impossible de créer ' . $dir);
        }

        $written = file_put_contents(
            $this->terrainsPath(),
            json_encode($terrains, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        );
        if ($written === false) {
            throw new RuntimeException('Impossible d\'écrire ' . $this->terrainsPath());
        }
    }

    /**
     * Garantit la section de couche (set de terrain « corner ») et la
     * retourne par référence.
     *
     * @param array<string, mixed> $terrains
     * @return array{name: string, type: string, colors: list<string>, tiles: array<string, mixed>}
     */
    public function &layerConfig(array &$terrains, string $layer): array
    {
        if (!isset($terrains[$layer])) {
            $terrains[$layer] = ['name' => 'Terrains', 'type' => 'corner', 'colors' => [], 'tiles' => []];
        }
        return $terrains[$layer];
    }

    /* ------------------------------------------------------------------ */
    /* Analyse                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Grilles du sol d'un plan en base, une par niveau z. Les constructions
     * des joueurs comptent aussi : leurs frontières se peignent pareil.
     *
     * @return array<int, array<string, string>> z => ["x,y" => nom de tuile]
     */
    public function gridsForPlan(string $plan, string $layer = self::GROUND_LAYER): array
    {
        if (!preg_match(TiledMapService::PLAN_NAME_PATTERN, $plan)) {
            throw new RuntimeException('Nom de plan invalide : ' . $plan);
        }
        if (!isset(TiledMapService::AUTHORABLE_LAYERS[$layer])) {
            throw new RuntimeException('Couche inconnue : ' . $layer);
        }

        $this->db ??= new Db();
        $res = $this->db->exe(
            'SELECT c.x, c.y, c.z, m.name
             FROM map_' . $layer . ' m
             JOIN coords c ON c.id = m.coords_id
             WHERE c.plan = ?',
            [$plan]
        );

        $grids = [];
        while ($row = $res->fetch_assoc()) {
            $grids[(int) $row['z']][$row['x'] . ',' . $row['y']] = $row['name'];
        }
        ksort($grids);

        return $grids;
    }

    /**
     * Ensembles de biomes qui se rencontrent aux points de coin d'une
     * grille : chaque intersection de 4 cases dont 2 à 4 tuiles pleines de
     * terrain diffèrent produit un ensemble (trié). Les tuiles hors terrain
     * (runes, escaliers, décor posé au sol) sont ignorées et remontées à
     * part.
     *
     * @param array<string, string> $grid ["x,y" => nom de tuile]
     * @param array{colors: list<string>, tiles: array<string, mixed>} $cfg
     * @return array{sets: array<string, list<string>>, ignored: list<string>}
     */
    public function cornerSets(array $grid, array $cfg): array
    {
        $sets = [];
        $ignored = [];

        $terrainAt = function (int $x, int $y) use ($grid, $cfg, &$ignored): ?string {
            $name = $grid["$x,$y"] ?? null;
            if ($name === null) {
                return null;
            }
            $spec = $cfg['tiles'][$name] ?? null;
            if (!is_string($spec)) {
                // Un fondu déjà posé (déclaré par wangId) n'est pas « hors
                // terrain » : sa frontière est déjà fondue, rien à signaler
                if (!is_array($spec)) {
                    $ignored[$name] = true;
                }
                return null;
            }
            return $name;
        };

        foreach ($grid as $key => $name) {
            [$x, $y] = array_map('intval', explode(',', $key));
            // les 4 points de coin de la case ; chaque point regarde ses 4 cases
            for ($cornerX = $x; $cornerX <= $x + 1; $cornerX++) {
                for ($cornerY = $y; $cornerY <= $y + 1; $cornerY++) {
                    $meeting = [];
                    for ($dx = -1; $dx <= 0; $dx++) {
                        for ($dy = -1; $dy <= 0; $dy++) {
                            $terrain = $terrainAt($cornerX + $dx, $cornerY + $dy);
                            if ($terrain !== null) {
                                $meeting[$terrain] = true;
                            }
                        }
                    }
                    if (count($meeting) >= 2) {
                        $set = array_keys($meeting);
                        sort($set);
                        $sets[implode('|', $set)] = $set;
                    }
                }
            }
        }

        $ignoredNames = array_keys($ignored);
        sort($ignoredNames);

        return ['sets' => $sets, 'ignored' => $ignoredNames];
    }

    /**
     * État des transitions d'un plan, sans rien écrire : ce que chaque
     * niveau z requiert, les ensembles incomplets et le volume à générer.
     *
     * @return array{
     *   plan: string, layer: string,
     *   zLevels: array<int, array{cells: int, pairs: int, trios: int, quads: int}>,
     *   sets: list<array{tiles: list<string>, missing: int, total: int, conflict: bool}>,
     *   ignored: list<string>,
     *   incompleteSets: int, missingTiles: int
     * }
     */
    public function auditPlan(string $plan, string $layer = self::GROUND_LAYER): array
    {
        $terrains = $this->loadTerrains();
        $cfg = &$this->layerConfig($terrains, $layer);

        $zLevels = [];
        $needed = [];
        $ignored = [];

        foreach ($this->gridsForPlan($plan, $layer) as $z => $grid) {
            ['sets' => $sets, 'ignored' => $zIgnored] = $this->cornerSets($grid, $cfg);
            $sizes = array_count_values(array_map('count', array_values($sets)));
            $zLevels[$z] = [
                'cells' => count($grid),
                'pairs' => $sizes[2] ?? 0,
                'trios' => $sizes[3] ?? 0,
                'quads' => $sizes[4] ?? 0,
            ];
            $needed += $sets;
            $ignored += array_fill_keys($zIgnored, true);
        }
        ksort($needed);

        $existing = $this->existingWangKeys($cfg);
        $setReports = [];
        $incomplete = 0;
        $missingTiles = 0;

        foreach ($needed as $set) {
            $conflict = $this->sameColorConflict($cfg, $set);
            $missing = $conflict ? 0 : count($this->missingTuples($cfg, $set, $existing));
            $setReports[] = [
                'tiles'    => $set,
                'missing'  => $missing,
                'total'    => count($this->surjectiveTuples(count($set))),
                'conflict' => $conflict,
            ];
            if ($missing > 0) {
                $incomplete++;
                $missingTiles += $missing;
            }
        }

        $ignoredNames = array_keys($ignored);
        sort($ignoredNames);

        return [
            'plan'           => $plan,
            'layer'          => $layer,
            'zLevels'        => $zLevels,
            'sets'           => $setReports,
            'ignored'        => $ignoredNames,
            'incompleteSets' => $incomplete,
            'missingTiles'   => $missingTiles,
        ];
    }

    /**
     * Génère toutes les transitions manquantes d'un plan et sauvegarde
     * terrains.json. Idempotent (comparaison par wangId, quel que soit
     * l'ordre historique des noms des fichiers existants).
     *
     * @return array{
     *   audit: array,
     *   generated: array<string, list<string>>,
     *   generatedCount: int,
     *   skipped: list<array{tiles: list<string>, reason: string}>
     * }
     */
    public function generateForPlan(string $plan, string $layer = self::GROUND_LAYER): array
    {
        $audit = $this->auditPlan($plan, $layer);

        $terrains = $this->loadTerrains();
        $cfg = &$this->layerConfig($terrains, $layer);
        $skipKeys = $this->existingWangKeys($cfg);

        $generated = [];
        $generatedCount = 0;
        $skipped = [];

        foreach ($audit['sets'] as $set) {
            if ($set['conflict']) {
                $skipped[] = [
                    'tiles'  => $set['tiles'],
                    'reason' => 'biomes de même couleur, transitions impossibles',
                ];
                continue;
            }
            if ($set['missing'] === 0) {
                continue;
            }
            $names = $this->generateSet($cfg, $layer, $set['tiles'], $skipKeys);
            if ($names !== []) {
                $generated[implode(' / ', $set['tiles'])] = $names;
                $generatedCount += count($names);
            }
        }

        if ($generatedCount > 0) {
            $this->saveTerrains($terrains);
        }

        return [
            'audit'          => $audit,
            'generated'      => $generated,
            'generatedCount' => $generatedCount,
            'skipped'        => $skipped,
        ];
    }

    /**
     * Classification des tuiles du sol d'un plan : chaque nom distinct posé
     * (tous niveaux z), avec son statut terrain (tuile pleine déclarée dans
     * terrains.json) et son nombre d'occurrences. Les fondus générés
     * (déclarés par wangId) sont signalés à part : ils ne se classent pas.
     *
     * @return list<array{name: string, isTerrain: bool, isTransition: bool, count: int}>
     */
    public function planTileClassification(string $plan, string $layer = self::GROUND_LAYER): array
    {
        $terrains = $this->loadTerrains();
        $cfg = &$this->layerConfig($terrains, $layer);

        $counts = [];
        foreach ($this->gridsForPlan($plan, $layer) as $grid) {
            foreach ($grid as $name) {
                $counts[$name] = ($counts[$name] ?? 0) + 1;
            }
        }
        ksort($counts);

        $classification = [];
        foreach ($counts as $name => $count) {
            $classification[] = [
                'name'         => $name,
                'isTerrain'    => is_string($cfg['tiles'][$name] ?? null),
                'isTransition' => is_array($cfg['tiles'][$name] ?? null),
                'count'        => $count,
            ];
        }

        return $classification;
    }

    /**
     * Classe des tuiles comme terrain / hors terrain et sauvegarde
     * terrains.json. Déclarer = tuile pleine de sa propre couleur (ajoutée
     * en FIN de liste : les wangId des fondus existants référencent les
     * couleurs par index, l'ordre ne doit jamais bouger). Déclasser =
     * retirer le mapping de tuile pleine SANS toucher à la liste des
     * couleurs ni aux fondus (une couleur orpheline est inoffensive, un
     * index décalé corromprait tous les wangId).
     *
     * Les entrées de fondus (trans_*, wangId) ne sont jamais modifiées.
     *
     * @param list<string> $terrainNames    tuiles à déclarer comme terrain
     * @param list<string> $nonTerrainNames tuiles à déclasser
     * @return array{declared: list<string>, undeclared: list<string>}
     */
    public function classifyTiles(string $layer, array $terrainNames, array $nonTerrainNames): array
    {
        $terrains = $this->loadTerrains();
        $cfg = &$this->layerConfig($terrains, $layer);

        $declared = [];
        foreach ($terrainNames as $name) {
            if (!preg_match(TileCatalogService::ASSET_NAME_PATTERN, $name)
                || is_array($cfg['tiles'][$name] ?? null)   // fondu : intouchable
                || is_string($cfg['tiles'][$name] ?? null)  // déjà terrain
            ) {
                continue;
            }
            if (!in_array($name, $cfg['colors'], true)) {
                $cfg['colors'][] = $name;
            }
            $cfg['tiles'][$name] = $name;
            $declared[] = $name;
        }

        $undeclared = [];
        foreach ($nonTerrainNames as $name) {
            if (is_string($cfg['tiles'][$name] ?? null)) {
                unset($cfg['tiles'][$name]);
                $undeclared[] = $name;
            }
        }

        if ($declared !== [] || $undeclared !== []) {
            $this->saveTerrains($terrains);
        }

        return ['declared' => $declared, 'undeclared' => $undeclared];
    }

    /**
     * Visibilité des tuiles de transition pour un plan : vraie pour celles
     * dont tous les biomes sont présents sur le sol du plan — restreint au
     * seul niveau $z s'il est fourni (un fondu ne traverse jamais deux
     * niveaux). Sert à l'éditeur web (scripts/tiled) pour ne pas noyer la
     * palette sous les milliers de fondus générés : poser un nouveau biome
     * puis générer ses transitions (admin/terrain-transitions.php) les fait
     * apparaître au rechargement de l'éditeur.
     *
     * @return array<string, bool> tuile trans_* déclarée => pertinente pour ce plan
     */
    public function transitionVisibilityForPlan(string $plan, ?int $z = null, string $layer = self::GROUND_LAYER): array
    {
        $terrains = $this->loadTerrains();
        $cfg = &$this->layerConfig($terrains, $layer);

        $names = [];
        foreach ($this->gridsForPlan($plan, $layer) as $gridZ => $grid) {
            if ($z === null || $gridZ === $z) {
                $names += array_flip($grid);
            }
        }

        $presentColors = [];
        foreach (array_keys($names) as $name) {
            $color = $cfg['tiles'][$name] ?? null;
            if (is_string($color)) {
                $index = array_search($color, $cfg['colors'], true);
                if ($index !== false) {
                    $presentColors[$index + 1] = true;
                }
            }
        }

        $visibility = [];
        foreach ($cfg['tiles'] as $name => $spec) {
            if (!is_array($spec)) {
                continue;
            }
            $visible = true;
            foreach ($spec as $colorIndex) {
                if ($colorIndex !== 0 && !isset($presentColors[$colorIndex])) {
                    $visible = false;
                    break;
                }
            }
            $visibility[$name] = $visible;
        }

        return $visibility;
    }

    /**
     * Fondus déclarés pertinents pour un plan (tous ses biomes présents,
     * tous niveaux z), groupés par ensemble de biomes — la vue « voir et
     * choisir quoi régénérer » de la page admin.
     *
     * @return array<string, list<string>> "couleurA / couleurB[ / …]" => noms de fondus
     */
    public function planTransitionsBySet(string $plan, string $layer = self::GROUND_LAYER): array
    {
        $terrains = $this->loadTerrains();
        $cfg = &$this->layerConfig($terrains, $layer);
        $visibility = $this->transitionVisibilityForPlan($plan, null, $layer);

        $bySet = [];
        foreach ($cfg['tiles'] as $name => $spec) {
            if (!is_array($spec) || empty($visibility[$name])) {
                continue;
            }
            $colorIndexes = array_values(array_unique(array_filter($spec)));
            sort($colorIndexes);
            $label = implode(' / ', array_map(
                fn(int $index) => $cfg['colors'][$index - 1] ?? ('#' . $index),
                $colorIndexes
            ));
            $bySet[$label][] = (string) $name;
        }
        ksort($bySet);
        foreach ($bySet as &$names) {
            sort($names);
        }

        return $bySet;
    }

    /**
     * Réécrit les PNG de fondus déclarés depuis les images de base
     * ACTUELLES, sans toucher aux wangId. À utiliser après un changement
     * d'art d'un biome, ou pour réparer des fondus corrompus — cas vécu :
     * blends tirant vers le noir, générés depuis des PNG à palette avant le
     * correctif imagepalettetotruecolor de loadTile.
     *
     * $names restreint aux fondus voulus (la page admin passe la sélection
     * de l'utilisateur, jamais tout le catalogue : des milliers de fichiers) ;
     * null = tous les fondus déclarés de la couche.
     *
     * Chaque nom est re-décomposé en tuiles composantes + code de coins,
     * et la décomposition est validée contre le wangId stocké (les noms de
     * tuiles peuvent contenir des underscores : seule la coupure dont les
     * couleurs correspondent est la bonne).
     *
     * @param list<string>|null $names
     * @return array{regenerated: int, unparsed: list<string>}
     */
    public function regenerateTransitionImages(string $layer = self::GROUND_LAYER, ?array $names = null): array
    {
        $terrains = $this->loadTerrains();
        $cfg = &$this->layerConfig($terrains, $layer);
        $imgDir = $this->root . '/img/' . $layer;

        $fullNames = array_keys(array_filter($cfg['tiles'], 'is_string'));
        $wanted = $names === null ? null : array_fill_keys($names, true);

        $regenerated = 0;
        $unparsed = [];

        foreach ($cfg['tiles'] as $name => $spec) {
            if (!is_array($spec) || ($wanted !== null && !isset($wanted[$name]))) {
                continue;
            }

            $parsed = $this->parseTransitionName((string) $name, $fullNames, $spec, $cfg);
            if ($parsed === null) {
                $unparsed[] = (string) $name;
                continue;
            }
            [$tileNames, $code] = $parsed;

            $images = array_map(fn(string $tile) => $this->loadTile($imgDir, $tile), $tileNames);
            $tuple = array_map(fn(string $letter) => ord($letter) - ord('a'), str_split($code));
            $blend = $this->blendCorners(array_map(fn(int $i) => $images[$i], $tuple));

            if (!imagepng($blend, $imgDir . '/' . $name . '.png')) {
                throw new RuntimeException('Écriture impossible : ' . $imgDir . '/' . $name . '.png');
            }
            $regenerated++;
        }

        return ['regenerated' => $regenerated, 'unparsed' => $unparsed];
    }

    /**
     * Décompose un nom de fondu (trans_<A>_<B>[_<C>[_<D>]]_<code>) en tuiles
     * composantes, dans l'ordre des lettres du code. Toutes les coupures
     * possibles en noms de tuiles pleines connus sont essayées ; celle dont
     * les couleurs reproduisent le wangId stocké gagne.
     *
     * @param list<string> $fullNames tuiles pleines déclarées
     * @param list<int> $wangId wangId stocké, la référence
     * @param array{colors: list<string>, tiles: array<string, mixed>} $cfg
     * @return array{list<string>, string}|null [tuiles, code] ou null
     */
    private function parseTransitionName(string $name, array $fullNames, array $wangId, array $cfg): ?array
    {
        if (!preg_match('/^trans_(.+)_([a-d]{4})$/', $name, $matches)) {
            return null;
        }
        $code = $matches[2];

        $letters = array_unique(str_split($code));
        sort($letters);
        $count = count($letters);
        if ($letters !== array_slice(['a', 'b', 'c', 'd'], 0, $count)) {
            return null;
        }

        $tuple = array_map(fn(string $letter) => ord($letter) - ord('a'), str_split($code));

        foreach ($this->tileNameSplits(explode('_', $matches[1]), $count, array_flip($fullNames)) as $tileNames) {
            $probe = $cfg;
            $indexes = array_map(fn(string $tile) => $this->colorIndexOf($probe, $tile), $tileNames);
            if ($this->tupleWangId($tuple, $indexes) === $wangId) {
                return [$tileNames, $code];
            }
        }

        return null;
    }

    /**
     * Toutes les coupures de morceaux d'un nom composé en $count noms de
     * tuiles connus (backtracking).
     *
     * @param list<string> $parts
     * @param array<string, int> $known noms de tuiles pleines (clés)
     * @return list<list<string>>
     */
    private function tileNameSplits(array $parts, int $count, array $known): array
    {
        if ($count === 1) {
            $name = implode('_', $parts);
            return isset($known[$name]) ? [[$name]] : [];
        }

        $splits = [];
        for ($i = 1; $i <= count($parts) - ($count - 1); $i++) {
            $head = implode('_', array_slice($parts, 0, $i));
            if (!isset($known[$head])) {
                continue;
            }
            foreach ($this->tileNameSplits(array_slice($parts, $i), $count - 1, $known) as $tail) {
                $splits[] = [$head, ...$tail];
            }
        }

        return $splits;
    }

    /* ------------------------------------------------------------------ */
    /* Génération                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Génère les tuiles de transition d'un ensemble de 2 à 4 biomes et
     * déclare leurs wangId dans $cfg (non sauvegardé : au appelant de
     * persister terrains.json). $skipKeys (clés wangKey) restreint aux
     * affectations encore absentes ; vide = tout (ré)générer.
     *
     * @param array{colors: list<string>, tiles: array<string, mixed>} $cfg modifié en place
     * @param list<string> $tileNames dans l'ordre des lettres du code
     * @param array<string, true> $skipKeys
     * @return list<string> noms des tuiles générées
     */
    public function generateSet(array &$cfg, string $layer, array $tileNames, array $skipKeys = []): array
    {
        $imgDir = $this->root . '/img/' . $layer;

        foreach ($tileNames as $name) {
            if (!preg_match(TileCatalogService::ASSET_NAME_PATTERN, $name)) {
                throw new RuntimeException('Nom de tuile invalide : ' . $name);
            }
        }
        if ($this->sameColorConflict($cfg, $tileNames)) {
            throw new RuntimeException('Biomes de même couleur, transitions impossibles : '
                . implode(', ', $tileNames));
        }
        if (!is_dir($imgDir) || !is_writable($imgDir)) {
            throw new RuntimeException('Dossier non inscriptible : ' . $imgDir);
        }

        $images = array_map(fn(string $name) => $this->loadTile($imgDir, $name), $tileNames);
        $indexes = array_map(fn(string $name) => $this->colorIndexOf($cfg, $name), $tileNames);

        $generated = [];
        foreach ($this->surjectiveTuples(count($tileNames)) as $tuple) {
            $wangId = $this->tupleWangId($tuple, $indexes);
            if (isset($skipKeys[$this->wangKey($wangId)])) {
                continue;
            }

            $code = implode('', array_map(fn(int $i) => chr(ord('a') + $i), $tuple));
            $name = ColorService::transitionTileName($tileNames, $code);

            $blend = $this->blendCorners(array_map(fn(int $i) => $images[$i], $tuple));
            if (!imagepng($blend, $imgDir . '/' . $name . '.png')) {
                throw new RuntimeException('Écriture impossible : ' . $imgDir . '/' . $name . '.png');
            }

            $cfg['tiles'][$name] = $wangId;
            $generated[] = $name;
        }

        return $generated;
    }

    /**
     * Deux biomes de l'ensemble partagent-ils la même couleur de terrain ?
     * (wangId incapable de les distinguer — transitions sans objet)
     *
     * @param array{colors: list<string>, tiles: array<string, mixed>} $cfg
     * @param list<string> $tileNames
     */
    public function sameColorConflict(array $cfg, array $tileNames): bool
    {
        $colors = [];
        foreach ($tileNames as $name) {
            $color = $cfg['tiles'][$name] ?? $name;
            $colors[] = is_string($color) ? $color : $name;
        }
        return count(array_unique($colors)) !== count($colors);
    }

    /**
     * Toutes les affectations de coins (TL,TR,BR,BL) sur $count biomes où
     * chaque biome apparaît au moins une fois : 14 pour 2, 36 pour 3, 24
     * pour 4.
     *
     * @return list<array{int, int, int, int}>
     */
    public function surjectiveTuples(int $count): array
    {
        $tuples = [];
        $total = $count ** self::CORNER_COUNT;

        for ($n = 0; $n < $total; $n++) {
            $tuple = [];
            $used = [];
            for ($c = self::CORNER_COUNT - 1, $rest = $n; $c >= 0; $c--) {
                $tuple[$c] = $rest % $count;
                $used[$tuple[$c]] = true;
                $rest = intdiv($rest, $count);
            }
            if (count($used) === $count) {
                ksort($tuple);
                $tuples[] = $tuple;
            }
        }

        return $tuples;
    }

    /** Clé d'identité d'un wangId de coins : couleurs TL,TR,BR,BL. */
    public function wangKey(array $wangId): string
    {
        return implode(',', array_map(fn(int $p) => $wangId[$p], self::WANG_POSITIONS));
    }

    /**
     * Clés des wangId déjà déclarés, pour ne pas regénérer un fondu
     * équivalent sous un autre nom.
     *
     * @param array{tiles: array<string, mixed>} $cfg
     * @return array<string, true>
     */
    public function existingWangKeys(array $cfg): array
    {
        $keys = [];
        foreach ($cfg['tiles'] as $spec) {
            if (is_array($spec)) {
                $keys[$this->wangKey($spec)] = true;
            }
        }
        return $keys;
    }

    /* ------------------------------------------------------------------ */
    /* Interne                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Affectations d'un ensemble encore absentes du set de terrain.
     *
     * @param array{colors: list<string>, tiles: array<string, mixed>} $cfg
     * @param list<string> $tileNames
     * @param array<string, true> $existingKeys
     * @return list<array{int, int, int, int}>
     */
    private function missingTuples(array $cfg, array $tileNames, array $existingKeys): array
    {
        // colorIndexOf déclare au besoin : travailler sur une copie pour
        // qu'un simple audit ne modifie pas la configuration
        $probe = $cfg;
        $indexes = array_map(fn(string $name) => $this->colorIndexOf($probe, $name), $tileNames);

        return array_values(array_filter(
            $this->surjectiveTuples(count($tileNames)),
            fn(array $tuple) => !isset($existingKeys[$this->wangKey($this->tupleWangId($tuple, $indexes))])
        ));
    }

    /**
     * @param array{int, int, int, int} $tuple index de biome par coin TL,TR,BR,BL
     * @param list<int> $indexes couleurs (1-based) des biomes
     * @return list<int> wangId Tiled (8 entrées)
     */
    private function tupleWangId(array $tuple, array $indexes): array
    {
        $wangId = [0, 0, 0, 0, 0, 0, 0, 0];
        foreach (self::WANG_POSITIONS as $corner => $position) {
            $wangId[$position] = $indexes[$tuple[$corner]];
        }
        return $wangId;
    }

    /** Index (1-based) de la couleur d'une tuile pleine, déclarée au besoin. */
    private function colorIndexOf(array &$cfg, string $tile): int
    {
        $color = $cfg['tiles'][$tile] ?? null;
        if (!is_string($color)) {
            $color = $tile;
            if (!in_array($color, $cfg['colors'], true)) {
                $cfg['colors'][] = $color;
            }
            $cfg['tiles'][$tile] = $color;
        }
        return (int) array_search($color, $cfg['colors'], true) + 1;
    }

    /** Charge une image de tuile (png/webp/gif) redimensionnée en 50x50, avec cache. */
    private function loadTile(string $dir, string $name): GdImage
    {
        if (isset($this->tileImages[$name])) {
            return $this->tileImages[$name];
        }

        $size = TiledMapService::TILE_SIZE;
        foreach (TileCatalogService::IMAGE_EXTENSIONS as $ext) {
            $path = $dir . '/' . $name . '.' . $ext;
            if (!file_exists($path)) {
                continue;
            }
            $image = match ($ext) {
                'png' => imagecreatefrompng($path),
                'webp' => imagecreatefromwebp($path),
                'gif' => imagecreatefromgif($path),
            };
            if (!$image) {
                break;
            }
            // PNG/GIF à palette : imagecolorat y renvoie l'INDEX de palette,
            // pas la couleur — le fondu tirait vers le noir sur les serveurs
            // dont le GD ne convertit pas en vraies couleurs au redimension-
            // nement (taille inchangée). Conversion explicite, partout.
            if (!imageistruecolor($image) && !imagepalettetotruecolor($image)) {
                break;
            }
            $scaled = imagescale($image, $size, $size);
            if ($scaled === false) {
                break;
            }
            imagealphablending($scaled, false);
            imagesavealpha($scaled, true);
            return $this->tileImages[$name] = $scaled;
        }

        throw new RuntimeException("Image introuvable ou illisible : $dir/$name.{png,webp,gif}");
    }

    /**
     * Poids bilinéaires des 4 coins (ordre TL,TR,BR,BL), calculés une seule
     * fois : en chaque pixel, la somme des 4 poids vaut 1.
     *
     * @return list<list<list<float>>>
     */
    private function weights(): array
    {
        if ($this->cornerWeights !== null) {
            return $this->cornerWeights;
        }

        $size = TiledMapService::TILE_SIZE;
        $weights = [[], [], [], []];
        for ($y = 0; $y < $size; $y++) {
            $v = $y / ($size - 1);
            for ($x = 0; $x < $size; $x++) {
                $u = $x / ($size - 1);
                $weights[0][$y][$x] = (1 - $u) * (1 - $v); // TL
                $weights[1][$y][$x] = $u * (1 - $v);       // TR
                $weights[2][$y][$x] = $u * $v;             // BR
                $weights[3][$y][$x] = (1 - $u) * $v;       // BL
            }
        }

        return $this->cornerWeights = $weights;
    }

    /**
     * Fondu des images de coins : chaque pixel est la somme des 4 images de
     * coin pondérée par les poids bilinéaires (le fondu A/B historique en
     * est le cas particulier à 2 images).
     *
     * @param array{GdImage, GdImage, GdImage, GdImage} $images par coin TL,TR,BR,BL
     */
    private function blendCorners(array $images): GdImage
    {
        $size = TiledMapService::TILE_SIZE;
        $weights = $this->weights();

        $out = imagecreatetruecolor($size, $size);
        imagealphablending($out, false);
        imagesavealpha($out, true);

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $alpha = $red = $green = $blue = 0.0;
                for ($c = 0; $c < self::CORNER_COUNT; $c++) {
                    $w = $weights[$c][$y][$x];
                    if ($w == 0.0) {
                        continue;
                    }
                    $pixel = imagecolorat($images[$c], $x, $y);
                    $alpha += (($pixel >> 24) & 0x7F) * $w;
                    $red   += (($pixel >> 16) & 0xFF) * $w;
                    $green += (($pixel >> 8) & 0xFF) * $w;
                    $blue  += ($pixel & 0xFF) * $w;
                }
                imagesetpixel($out, $x, $y, ((int) round($alpha) << 24)
                    | ((int) round($red) << 16) | ((int) round($green) << 8) | (int) round($blue));
            }
        }

        return $out;
    }
}
