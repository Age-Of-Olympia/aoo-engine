<?php
/**
 * Génère les tuiles de transition entre biomes pour l'autotiling Tiled
 * (pinceau Terrain), et déclare leurs wangId dans tools/tiled/aoo/terrains.json.
 * Le moteur (analyse de coins, fondus, wangId) vit dans
 * App\Service\TerrainTransitionService — partagé avec le panneau admin des
 * cartes locales (admin/local_maps.php), qui fait la même chose sur un plan
 * vivant en base.
 *
 * Usage (dans le devcontainer) :
 *   php tools/tiled/generate_transitions.php tiles carreaux desert_de_l_egeon
 *   php tools/tiled/generate_transitions.php --all tiles   # toutes les paires
 *   php tools/tiled/generate_transitions.php --seed tiles [exclues...]
 *   php tools/tiled/generate_transitions.php --map tiles tools/tiled/maps/test/olympia.tmj
 *
 * Produit img/<couche>/trans_<A>_<B>[_<C>[_<D>]]_<code>.png : une tuile par
 * affectation de coins (code = 4 lettres a/b/c/d dans l'ordre TL,TR,BR,BL,
 * chaque biome présent au moins une fois — 14 tuiles pour une paire, 36 pour
 * un trio, 24 pour un quatuor), par fondu bilinéaire des images de base
 * pondéré par les 4 coins. Le jeu les rend comme n'importe quelle tuile
 * (img/<table>/<name>.png, Classes/View.php) et la carte du jeu mélange leurs
 * couleurs (ColorService::colorFor) — img/ n'étant pas versionné, penser à
 * reporter les PNG générés dans la source d'assets déployée.
 *
 * --all génère chaque paire (non ordonnée) de tuiles pleines déclarées dans
 * terrains.json pour la couche. Relançable : écrase les PNG et resynchronise
 * les entrées de terrains.json. Attention au volume : N biomes déclarés =
 * N(N-1)/2 paires × 14 PNG — préférer --map ou les paires ciblées au-delà
 * de ~10.
 *
 * --seed déclare comme biome (couleur + tuile pleine) chaque tuile posable du
 * catalogue de la couche (mêmes règles que la palette de l'éditeur : taille
 * ~50x50), sauf les noms passés en arguments et les transitions générées.
 * Ne produit aucun PNG : générer ensuite les transitions des paires utiles.
 *
 * --map analyse une ou plusieurs cartes .tmj pullées (tools/tiled/maps/) et
 * génère exactement ce que le pinceau Terrain y requiert : pour chaque point
 * de coin de la carte (intersection de 4 cases), les 2, 3 ou 4 biomes qui s'y
 * rencontrent forment un ensemble dont toutes les tuiles de transition
 * doivent exister — les jonctions à 3 biomes sont la cause des « morceaux
 * d'autres terrains » que pose le pinceau quand il ne trouve pas de tuile
 * exacte. Idempotent : ne génère que les combinaisons absentes (comparaison
 * par wangId, quel que soit l'ordre des noms dans les fichiers existants).
 */

use App\Service\TerrainTransitionService;
use App\Service\TileCatalogService;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$mode = $argv[1] ?? '';
$all = $mode === '--all';
$seed = $mode === '--seed';
$mapMode = $mode === '--map';

// --all prend 1 argument (la couche), --seed la couche + exclusions
// optionnelles, --map la couche + au moins une carte, sinon couche + deux tuiles
$argsOk = match (true) {
    $all     => $argc === 3,
    $seed    => $argc >= 3,
    $mapMode => $argc >= 4,
    default  => $argc === 4,
};
if (!$argsOk) {
    fwrite(STDERR, "Usage: php tools/tiled/generate_transitions.php <couche> <tuileA> <tuileB>\n");
    fwrite(STDERR, "       php tools/tiled/generate_transitions.php --all <couche>\n");
    fwrite(STDERR, "       php tools/tiled/generate_transitions.php --seed <couche> [tuiles_a_exclure...]\n");
    fwrite(STDERR, "       php tools/tiled/generate_transitions.php --map <couche> <carte.tmj> [autres.tmj...]\n");
    exit(1);
}

$layer = ($all || $seed || $mapMode) ? $argv[2] : $argv[1];
$root = dirname(__DIR__, 2);

/* ------------------------------------------------------------------ */
/* Lecture des cartes .tmj (--map)                                     */
/* ------------------------------------------------------------------ */

/** Décode la grille d'une couche de tuiles .tmj (tableau brut ou base64±zlib). */
function decodeLayerData(array $layerJson, array $chunk): array
{
    $data = $chunk['data'];
    if (is_array($data)) {
        return $data;
    }

    $binary = base64_decode($data, true);
    if ($binary === false) {
        fwrite(STDERR, "Données de couche illisibles (base64)\n");
        exit(1);
    }
    $binary = match ($layerJson['compression'] ?? '') {
        ''     => $binary,
        'zlib' => gzuncompress($binary),
        'gzip' => gzdecode($binary),
        default => null,
    };
    if (!is_string($binary)) {
        fwrite(STDERR, 'Compression de couche non gérée : ' . ($layerJson['compression'] ?? '?') . "\n");
        exit(1);
    }

    return array_values(unpack('V*', $binary));
}

/** Valeur d'une propriété Tiled personnalisée d'un nœud .tmj. */
function tmjProperty(array $node, string $name): ?string
{
    foreach ($node['properties'] ?? [] as $property) {
        if (($property['name'] ?? '') === $name) {
            return (string) $property['value'];
        }
    }
    return null;
}

/**
 * Grilles de noms de tuiles d'une carte .tmj, une par groupe de niveau z
 * (clé = nom du groupe), pour la couche demandée. Les couches verrouillées
 * « (joueurs) » comptent aussi : leurs frontières se peignent pareil.
 *
 * @return array<string, array<string, string>> groupe => ["x,y" => nom]
 */
function tmjGrids(string $path, string $layer): array
{
    $map = json_decode((string) file_get_contents($path), true);
    if (!is_array($map)) {
        fwrite(STDERR, "Carte illisible : $path\n");
        exit(1);
    }

    $gidToName = [];
    foreach ($map['tilesets'] ?? [] as $tileset) {
        if (tmjProperty($tileset, 'aooLayer') !== $layer) {
            continue;
        }
        foreach ($tileset['tiles'] ?? [] as $tile) {
            $name = tmjProperty($tile, 'aooName');
            if ($name !== null) {
                $gidToName[$tileset['firstgid'] + $tile['id']] = $name;
            }
        }
    }

    $grids = [];
    $walk = function (array $layers, string $group) use (&$walk, &$grids, $gidToName, $layer): void {
        foreach ($layers as $node) {
            if (($node['type'] ?? '') === 'group') {
                $walk($node['layers'] ?? [], $node['name']);
                continue;
            }
            if (($node['type'] ?? '') !== 'tilelayer' || tmjProperty($node, 'aooLayer') !== $layer) {
                continue;
            }
            $chunks = $node['chunks'] ?? [array_merge($node, [
                'x' => $node['startx'] ?? 0, 'y' => $node['starty'] ?? 0,
            ])];
            foreach ($chunks as $chunk) {
                $data = decodeLayerData($node, $chunk);
                foreach ($data as $i => $gid) {
                    $gid &= 0x0FFFFFFF; // sans les bits de miroir/rotation
                    if ($gid !== 0 && isset($gidToName[$gid])) {
                        $x = $chunk['x'] + $i % $chunk['width'];
                        $y = $chunk['y'] + intdiv($i, $chunk['width']);
                        $grids[$group]["$x,$y"] = $gidToName[$gid];
                    }
                }
            }
        }
    };
    $walk($map['layers'] ?? [], '');

    return $grids;
}

/* ------------------------------------------------------------------ */

$service = new TerrainTransitionService(null, $root);

try {
    $terrains = $service->loadTerrains();
    $cfg = &$service->layerConfig($terrains, $layer);

    if ($seed) {
        // Déclarer chaque tuile posable du catalogue comme biome, sans PNG.
        $_SERVER['DOCUMENT_ROOT'] = $root; // TileCatalogService lit img/ depuis la racine web
        $excluded = array_slice($argv, 3);
        ['catalog' => $catalog] = (new TileCatalogService())->buildCatalog([$layer]);

        $added = 0;
        foreach ($catalog[$layer] as $name) {
            if (str_starts_with($name, 'trans_')
                || preg_match('/-\d{2}$/', $name) // morceau de structure composite, pas un terrain
                || in_array($name, $excluded, true)
                || isset($cfg['tiles'][$name])
            ) {
                continue;
            }
            if (!in_array($name, $cfg['colors'], true)) {
                $cfg['colors'][] = $name;
            }
            $cfg['tiles'][$name] = $name;
            $added++;
        }

        echo "$added biome(s) déclaré(s) pour la couche $layer (" . count($cfg['colors']) . " couleurs au total)\n";
        echo "Rappel : préférer --map (génère ce que les cartes requièrent) à --all.\n";
    } elseif ($mapMode) {
        // Ce que les cartes requièrent, moins ce qui existe déjà (par wangId).
        $needed = [];
        $ignored = [];

        foreach (array_slice($argv, 3) as $mapPath) {
            foreach (tmjGrids($mapPath, $layer) as $group => $grid) {
                ['sets' => $sets, 'ignored' => $groupIgnored] = $service->cornerSets($grid, $cfg);
                $counts = array_count_values(array_map('count', array_values($sets)));
                echo basename($mapPath) . ' ' . $group . ' : ' . count($grid) . ' cases, ensembles requis : '
                    . ($counts[2] ?? 0) . ' paires, ' . ($counts[3] ?? 0) . ' trios, '
                    . ($counts[4] ?? 0) . " quatuors\n";
                $needed += $sets;
                $ignored += array_fill_keys($groupIgnored, true);
            }
        }

        if ($ignored !== []) {
            $names = array_keys($ignored);
            sort($names);
            echo 'Tuiles hors terrain ignorées : ' . implode(', ', $names) . "\n";
        }

        $skipKeys = $service->existingWangKeys($cfg);
        $generated = 0;
        $combos = 0;
        ksort($needed);

        foreach ($needed as $set) {
            if ($service->sameColorConflict($cfg, $set)) {
                echo '  ! ' . implode(' / ', $set) . " : biomes de même couleur, ignoré\n";
                continue;
            }
            $names = $service->generateSet($cfg, $layer, $set, $skipKeys);
            if ($names !== []) {
                echo '  + ' . implode(' / ', $set) . ' : ' . count($names) . " tuiles\n";
                $generated += count($names);
                $combos++;
            }
        }

        echo count($needed) . " ensembles requis, $combos incomplets → $generated tuiles générées dans img/$layer/\n";
    } elseif ($all) {
        // Toutes les paires (non ordonnées) de tuiles pleines déclarées
        $fullTiles = array_keys(array_filter($cfg['tiles'], 'is_string'));
        $generated = 0;
        $pairs = 0;

        foreach ($fullTiles as $i => $tileA) {
            foreach (array_slice($fullTiles, $i + 1) as $tileB) {
                $generated += count($service->generateSet($cfg, $layer, [$tileA, $tileB]));
                $pairs++;
            }
        }

        echo "$generated tuiles générées pour $pairs paires dans img/$layer/\n";
    } else {
        [, , $tileA, $tileB] = $argv;
        $generated = count($service->generateSet($cfg, $layer, [$tileA, $tileB]));
        echo "$generated tuiles de transition générées dans img/$layer/ (trans_{$tileA}_{$tileB}_*.png)\n";
    }

    $service->saveTerrains($terrains);
    echo "terrains.json mis à jour — re-puller un plan pour recharger les tilesets.\n";
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
