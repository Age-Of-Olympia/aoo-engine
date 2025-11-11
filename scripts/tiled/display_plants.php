<?php
use Classes\File;

echo '<details>';
echo '<summary style="cursor: pointer; font-weight: bold; margin: 10px 0;"><h3 style="display: inline;">Plantes (recoltables, passables)</h3></summary>';

echo '
<div>
';

foreach(File::scan_dir('img/plants/', without:".png") as $e){

    echo '<img
        class="map plants select-name"
        data-type="plants"
        data-name="'. $e .'"
        src="img/plants/'. $e .'.png"
        loading="lazy"
    />';
}

echo '
</div>
</details>
';

