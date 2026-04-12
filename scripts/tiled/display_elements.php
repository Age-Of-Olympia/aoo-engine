<?php
use Classes\File;

echo '<details>';
echo '<summary style="cursor: pointer; font-weight: bold; margin: 10px 0;"><h3 style="display: inline;">Elements (ajoute un effet, passables)</h3></summary>';

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
        loading="lazy"
    />';
}

echo '
</div>
</details>
';


