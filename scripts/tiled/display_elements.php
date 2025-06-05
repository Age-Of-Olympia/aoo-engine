<?php
use Classes\File;

echo '<h3>Elements (ajoute un effet, passables)</h3>';

echo '
<div>
';

foreach(File::scan_dir('img/elements/') as $e){

    if(explode('.', $e)[1] == 'gif'){

        continue;
    }

    echo '<img
        class="map ele"
        data-type="elements"
        data-element="'. explode('.', $e)[0] .'"
        data-name="'. explode('.', $e)[0] .'"
        src="img/elements/'. $e .'"
    />';
}

echo '
</div>
';


