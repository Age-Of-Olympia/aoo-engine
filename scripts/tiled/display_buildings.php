<?php
use App\Enum\EntityCategory;
use App\Service\BuildingService;
use App\Service\RaceService;

/* Palette des types de structure (mêmes entrées que admin → Bâtiments) :
   poser une tuile crée une ENTITÉ bâtiment (BuildingService::place),
   pas une ligne map_*. La gomme et « info » savent les retirer. */

echo '<details>';
echo '<summary style="cursor: pointer; font-weight: bold; margin: 10px 0;"><h3 style="display: inline;">Bâtiments (entités — murs, statues, coffres…)</h3></summary>';

echo '
<div>
<p style="font-size: 0.85em; color: #999; margin: 5px 0;">Chaque pose crée une entité (PV de son type, attaquable). Propriétaire, faction et dialogue se règlent dans admin → Bâtiments.</p>
';

/* L'emprise du type accompagne l'outil (data-figure) : le curseur d'objet
   de l'éditeur montre la figure entière avant la pose — même source que la
   pose elle-même. */
$footprints = (new \App\Service\Map\EntityTypeFootprintService())->catalogue();

foreach((new RaceService())->getRacesByKind(EntityCategory::Structure->value) as $race){

    $name = $race->getName();
    $sprite = BuildingService::resolveAvatar($name);

    if($sprite === BuildingService::NO_IMAGE){

        continue; /* sans visuel : posable via admin → Bâtiments, pas au pinceau */
    }

    $footprint = $footprints[$name] ?? null;

    echo '<img
        class="map wall select-name"
        data-type="buildings"
        data-name="'. $name .'"'
        . ($footprint !== null && $footprint->cells() > 1
            ? ' data-figure=\''. json_encode([
                'w' => $footprint->width(),
                'h' => $footprint->height(),
                'img' => $sprite,
            ], JSON_UNESCAPED_SLASHES) .'\''
            : '') . '
        title="'. htmlspecialchars($race->getLabel(), ENT_QUOTES) .'"
        src="'. $sprite .'"
        loading="lazy"
    />';


}

echo '
</div>
</details>
';
