<?php
use Classes\File;

echo '<h3>Foregrounds (indestructibles, passables)</h3>';

echo '
<div>
';

foreach(File::scan_dir('img/foregrounds/', without:".png") as $e){


    $url = 'img/foregrounds/'. $e .'.png';

    if(!file_exists($url)){

        continue;
    }


    echo '<img
        class="map foregrounds select-name"
        data-type="foregrounds"
        data-name="'. $e .'"
        src="'. $url .'"
        width="50"
    />';
}

echo '
</div>
';
