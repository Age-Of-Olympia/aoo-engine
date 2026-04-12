<?php
use Classes\File;

echo '<details open>';
echo '<summary style="cursor: pointer; font-weight: bold; margin: 10px 0;"><h3 style="display: inline;">Tiles (indestructibles, passables)</h3></summary>';

echo '
<div>
';

foreach(File::scan_dir('img/tiles/', without:".png") as $e){


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
        loading="lazy"
    />';
}

echo '
</div>
</details>
';
