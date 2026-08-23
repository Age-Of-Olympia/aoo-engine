<?php
use App\Service\TriggerPaletteService;

echo '<details>';
echo '<summary style="cursor: pointer; font-weight: bold; margin: 10px 0;"><h3 style="display: inline;">Déclencheurs (invisibles)</h3></summary>';

echo '
<div>
';

/* La palette suit les gestionnaires, pas les images : une image survit au
   retrait du code qui l'exécute, et proposait des déclencheurs inertes. */
foreach(TriggerPaletteService::playableNames() as $e){


    $params = '';


    if($e == 'tp'){

        $params = 'x,y,z,plan';
    }
    elseif($e == 'need'){

        $params = 'item:name:n,spell:spell_name';
    }
    elseif($e == 'grow'){

        /* Point de pousse : params = la plante semée par le cron nocturne */
        $params = 'adonis';
    }


    echo '<img
        class="map trigger select-name"
        data-type="triggers"
        data-params="'. $params .'"
        data-name="'. $e .'"
        src="img/triggers/'. $e .'.png"
        loading="lazy"
    />';
}


echo '<div>Params: <input type="text" id="triggers-params" /></div>';


echo '
</div>
</details>
';

