<?php
use Classes\File;

echo '<details>';
echo '<summary style="cursor: pointer; font-weight: bold; margin: 10px 0;"><h3 style="display: inline;">Foregrounds (indestructibles, passables)</h3></summary>';

// Separate regular and unique foregrounds
$regularForegrounds = [];
$uniqueForegrounds = [];

foreach(File::scan_dir('img/foregrounds/', without:".png") as $e){
    $url = 'img/foregrounds/'. $e .'.png';

    if(!file_exists($url)){
        continue;
    }

    if(str_starts_with($e, 'unique_')){
        $uniqueForegrounds[] = ['name' => $e, 'url' => $url];
    } else {
        $regularForegrounds[] = ['name' => $e, 'url' => $url];
    }
}

/* La palette montre des OBJETS, pas des morceaux.
 *
 * Elle listait 715 vignettes — `geant_petrifie-00`, `-01`, `-02`, `-03` —
 * sans dire qu'elles font UN géant. On choisissait un morceau en croyant
 * choisir un décor, et il fallait connaître la découpe pour reconstituer la
 * figure à la main.
 *
 * Les découpes sont dérivées de la carte (SceneryFootprintDeriver) ; ce qui
 * n'en a pas — un décor d'une seule case — reste listé tel quel. */
$catalogue = (new \App\Service\Map\SceneryFootprintDeriver())->catalogue();

echo \App\View\Tiled\SceneryPaletteView::render($regularForegrounds, $catalogue);

// Display unique foregrounds in a subsection if any exist
if(!empty($uniqueForegrounds)){
    echo '<details style="margin-top: 10px;">';
    echo '<summary style="cursor: pointer; font-weight: bold; padding: 5px; background: rgba(0, 0, 0, 0.05); border-radius: 4px;"><h4 style="display: inline; font-size: 14px;">Uniques</h4></summary>';
    echo '<div style="margin-top: 5px;">';

    echo \App\View\Tiled\SceneryPaletteView::render($uniqueForegrounds, $catalogue);

    echo '</div>';
    echo '</details>';
}

echo '</details>
';
