<?php


echo '<h3>Météos (ajoute une météo sur la case)</h3>';

echo '
<div>
';

foreach(File::scan_dir('img/weather_tiled/') as $e){

    if(explode('.', $e)[1] == 'gif'){

        continue;
    }

    echo '<img
        class="map weather select-name"
        data-type="weather"
        data-element="'. explode('.', $e)[0] .'"
        data-name="'. explode('.', $e)[0] .'"
        src="img/weather_tiled/'. $e .'"
    />';
}

echo '
</div>
';


