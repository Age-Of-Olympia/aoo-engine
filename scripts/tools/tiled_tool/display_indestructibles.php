<?php

echo '<h3>Tiles (indestructibles, passables)</h3>';

echo '
<div>
';

foreach(File::scan_dir('img/tiles/', $without=".png") as $e){


    $url = 'img/tiles/'. $e .'.png';

    if(!file_exists($url)){

        continue;
    }


    echo '<img
        class="map tile select-name"
        data-type="tiles"
        data-name="'. $e .'"
        src="'. $url .'"
        width="50"
    />';
}

echo '
</div>
';
