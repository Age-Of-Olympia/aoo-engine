<?php
use App\Service\ResourcePaletteService;
use Classes\File;


echo '<details>';
echo '<summary style="cursor: pointer; font-weight: bold; margin: 10px 0;"><h3 style="display: inline;">Ressources (récoltables &amp; spéciaux)</h3></summary>';

echo '
<div>
<p style="font-size: 0.85em; color: #999; margin: 5px 0;">Les obstacles et le décor (murs, statues, coffres…) sont des entités : ils se posent dans la palette Bâtiments ci-dessous.</p>
';

$hidden = 0;

foreach(File::scan_dir('img/walls/', $without=".png") as $e){


    $url = 'img/walls/'. $e .'.png';

    if(!file_exists($url)){

        continue;
    }

    /* Depuis la conversion des obstacles en entités bâtiment, seuls les
       murs encore posables en map_resources sur ce plan restent proposés */
    if(!ResourcePaletteService::isAuthorable($e, $player->coords->plan)){

        $hidden++;
        continue;
    }

    echo '<img
        class="map wall select-name"
        data-type="resources"
        data-params="damages"
        data-name="'. $e .'"
        src="'. $url .'"
        loading="lazy"
    />';


}
echo '<div>Damages: <input type="text" id="resources-params" /></div>';

if($hidden > 0){

    echo '<p style="font-size: 0.85em; color: #999;">'. $hidden .' obstacle(s) masqué(s) — devenus des bâtiments.</p>';
}

echo '
</div>
</details>
';
