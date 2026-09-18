<?php
use App\Service\TerrainTransitionService;
use Classes\File;

/* Les fondus de transition générés (trans_*) se comptent par milliers :
   n'afficher que ceux dont tous les biomes sont présents sur le niveau z
   édité (un fondu ne traverse jamais deux niveaux), sinon la palette noie
   les vraies tuiles. Poser un nouveau biome puis générer ses transitions
   (admin/local_maps.php, carte « Transitions de terrain ») les fait
   apparaître au rechargement de l'éditeur. En cas d'échec de l'analyse
   (terrains.json absent…), tout afficher comme avant. */
$transitionVisibility = null;
try {
    $transitionVisibility = (new TerrainTransitionService())
        ->transitionVisibilityForPlan($player->coords->plan, (int) $player->coords->z);
} catch (Throwable $transitionError) {
    /* palette non filtrée */
}

echo '<details open>';
echo '<summary style="cursor: pointer; font-weight: bold; margin: 10px 0;"><h3 style="display: inline;">Tiles (indestructibles, passables)</h3></summary>';

echo '
<div>
';

$hiddenTransitions = 0;

foreach(File::scan_dir('img/tiles/') as $file){

    // Any tile format, svg included; the name is the file without its extension
    if(!in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), \App\Service\TileCatalogService::IMAGE_EXTENSIONS, true)){

        continue;
    }
    $e = pathinfo($file, PATHINFO_FILENAME);
    $url = 'img/tiles/'. $file;

    // The plan backgrounds and weather textures live here too: only tile-sized images are brushes
    $size = \App\Service\TileCatalogService::imageSize($url);
    if(!$size || max($size) > \App\Service\TiledMapService::TILE_SIZE * 1.2){

        continue;
    }

    if($transitionVisibility !== null && str_starts_with($e, 'trans_') && empty($transitionVisibility[$e])){

        $hiddenTransitions++;
        continue;
    }


    echo '<img
        class="map tile select-name"
        data-type="tiles"
        data-name="'. $e .'"
        src="'. $url .'"
        width="50"
        loading="lazy"
    />';
}

echo '
</div>
';

if($hiddenTransitions > 0){

    echo '<p style="font-size: 11px; color: #888; margin: 4px 0;">'
        . $hiddenTransitions . ' fondus de transition masqués (biomes absents de ce niveau z) — '
        . 'poser un biome et générer ses transitions les fait apparaître au rechargement.</p>';
}

echo '
</details>
';
