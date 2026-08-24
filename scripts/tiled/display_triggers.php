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

        /* Growth spot: params is the plant the nightly cron sows here */
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

/* The prefilled template shows one format only; the variants are listed
   next to the palette that asks for them. */
echo '<div style="font-size: 0.85em; opacity: 0.8; margin-top: 4px;">'
    . '<code>tp</code> : <code>x,y,z,plan</code> — un segment non numérique (ou <code>plan</code>)'
    . ' garde la valeur du joueur. Une condition facultative se met en cinquième :'
    . ' <code>x,y,z,plan,item:clef:1</code>, et le passage se refuse sans elle.<br />'
    . '<code>need</code> : <code>item:nom:n,spell:nom</code> — les termes se cumulent.<br />'
    . '<code>grow</code> : le nom de la plante qui pousse là.'
    . '</div>';


echo '
</div>
</details>
';

