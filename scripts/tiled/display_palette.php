<?php
/* A plain palette: every image of img/<$layer>/, posted by name. */
use Classes\File;

echo '<details>';
echo '<summary style="cursor: pointer; font-weight: bold; margin: 10px 0;"><h3 style="display: inline;">'. $title .'</h3></summary>';

echo '
<div>
';

foreach(File::scan_dir('img/'. $layer .'/') as $e){

    echo '<img
        class="map select-name"
        data-type="'. $layer .'"
        data-name="'. explode('.', $e)[0] .'"
        src="img/'. $layer .'/'. $e .'"
        loading="lazy"
    />';
}

echo '
</div>
</details>
';
