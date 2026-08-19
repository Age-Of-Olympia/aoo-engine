<?php
/**
 * Admin API : synchronisation des images de l'éditeur Tiled.
 *
 * GET /api/admin/map/images.php            → { success, images: { "img/tiles/x.png": "<md5>", … } }
 * GET /api/admin/map/images.php?path=<rel> → { success, path, data } (contenu en base64)
 *
 * Auth : header X-AoO-Tiled-Token (jeton délivré par auth.php).
 *
 * L'inventaire est l'ensemble exact des images que le catalogue référence
 * (palettes des couches, originales des structures, fonds/masques, sprites
 * de bâtiment) ; le téléchargement n'accepte qu'un chemin membre de cet
 * inventaire — aucun chemin n'est résolu depuis l'entrée utilisateur. Les
 * md5 permettent à l'extension de ne rapatrier que les images manquantes
 * ou modifiées, dans un magasin local par instance.
 */

use App\Enum\EntityCategory;
use App\Service\BuildingService;
use App\Service\RaceService;
use App\Service\TileCatalogService;
use App\Service\TiledMapService;

require_once __DIR__ . '/_common.php';

tiledRequireAdmin();

/** @return array<string, string> chemin relatif img/… → chemin absolu */
function tiledImageInventory(): array
{
    $catalog = new TileCatalogService();
    $layers = array_keys(TiledMapService::AUTHORABLE_LAYERS);
    $compositeLayers = array_keys(array_filter(
        TiledMapService::AUTHORABLE_LAYERS,
        fn(array $spec) => $spec['composites']
    ));

    $paths = array_values($catalog->buildCatalog($layers)['images']);
    foreach ($catalog->buildComposites($compositeLayers) as $entries) {
        foreach ($entries as $composite) {
            $paths[] = $composite['image'];
        }
    }
    // Fonds et masques proposés par l'admin ; un bg exotique hors de cette
    // liste ne se synchronise pas, l'aperçu retombe sur le dossier local.
    foreach ($catalog->backgroundChoices() as $choice) {
        $paths[] = $choice;
    }
    foreach ((new RaceService())->getRacesByKind(EntityCategory::Structure->value) as $race) {
        $sprite = BuildingService::resolveAvatar($race->getName());
        if ($sprite !== BuildingService::NO_IMAGE) {
            $paths[] = $sprite;
        }
    }

    $root = rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/');
    $inventory = [];
    foreach ($paths as $path) {
        $absolute = $root . '/' . $path;
        if (is_file($absolute)) {
            $inventory[$path] = $absolute;
        }
    }
    ksort($inventory);

    return $inventory;
}

$inventory = tiledImageInventory();

$path = (string) ($_GET['path'] ?? '');
if ($path !== '') {
    if (!isset($inventory[$path])) {
        tiledFail(404, 'Image hors inventaire : ' . $path);
    }

    tiledSucceed(['path' => $path, 'data' => base64_encode((string) file_get_contents($inventory[$path]))]);
}

tiledSucceed(['images' => array_map(md5_file(...), $inventory)]);
