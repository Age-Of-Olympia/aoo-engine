<?php
use Classes\File;

echo '<details>';
echo '<summary style="cursor: pointer; font-weight: bold; margin: 10px 0;"><h3 style="display: inline;">Marques (sans effet, au-dessus des éléments)</h3></summary>';

echo '
<div>
';

foreach(File::scan_dir('img/marks/') as $e){

    echo '<img
        class="map select-name"
        data-type="marks"
        data-element="'. explode('.', $e)[0] .'"
        data-name="'. explode('.', $e)[0] .'"
        src="img/marks/'. $e .'"
        loading="lazy"
    />';
}

echo '
</div>
</details>
';
