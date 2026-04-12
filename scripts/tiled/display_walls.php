<?php
use Classes\File;


echo '<details>';
echo '<summary style="cursor: pointer; font-weight: bold; margin: 10px 0;"><h3 style="display: inline;">Walls (destructibles, non passables)</h3></summary>';

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
        data-params="damages"
        data-name="'. $e .'"
        src="'. $url .'"
        loading="lazy"
    />';


}
echo '<div>Damages: <input type="text" id="walls-params" /></div>';

echo '
</div>
</details>
';

