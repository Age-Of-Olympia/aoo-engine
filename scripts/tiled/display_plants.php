<?php


echo '<h3>Plantes (recoltables, passables)</h3>';

echo '
<div>
';

foreach(File::scan_dir('img/plants/', without:".png") as $e){

    echo '<img
        class="map plants select-name"
        data-type="plants"
        data-name="'. $e .'"
        src="img/plants/'. $e .'.png"
    />';
}

echo '
</div>
';

