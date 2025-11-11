<?php
use Classes\File;

echo '<details>';
echo '<summary style="cursor: pointer; font-weight: bold; margin: 10px 0;"><h3 style="display: inline;">Route</h3></summary>';

echo '
<div>
';

foreach(File::scan_dir('img/routes/') as $e){

    echo '<img
        class="map route select-name"
        data-type="routes"
        data-element="'. explode('.', $e)[0] .'"
        data-name="'. explode('.', $e)[0] .'"
        src="img/routes/'. $e .'"
        loading="lazy"
    />';
}

echo '
</div>
</details>
';


