<?php
use Classes\File;

echo '<details>';
echo '<summary style="cursor: pointer; font-weight: bold; margin: 10px 0;"><h3 style="display: inline;">Foregrounds (indestructibles, passables)</h3></summary>';

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
        loading="lazy"
    />';
}

echo '
</div>
</details>
';
