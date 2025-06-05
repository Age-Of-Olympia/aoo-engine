<?php
use Classes\File;


echo '<h3>Walls (destructibles, non passables)</h3>';

echo '
<div>
';

foreach(File::scan_dir('img/walls/', $without=".png") as $e){


    $url = 'img/walls/'. $e .'.png';

    if(!file_exists($url)){

        continue;
    }

    echo '<img
        class="map wall select-name"
        data-type="walls"
        data-name="'. $e .'"
        src="'. $url .'"
    />';
}

echo '
</div>
';

