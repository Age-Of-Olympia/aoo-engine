<?php
use Classes\File;

echo '<h3>Route</h3>';

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
    />';
}

echo '
</div>
';


