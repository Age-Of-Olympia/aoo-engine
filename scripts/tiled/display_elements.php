<?php

echo '<details>';
echo '<summary style="cursor: pointer; font-weight: bold; margin: 10px 0;"><h3 style="display: inline;">Elements (ajoute un effet, passables)</h3></summary>';

echo '
<div>
';

$elementImages = new \App\Service\MapElementService();
foreach($elementImages->placeableNames() as $name){

    // One image per name, the one the board draws
    echo '<img
        class="map ele"
        data-type="elements"
        data-element="'. $name .'"
        data-name="'. $name .'"
        src="'. $elementImages->imagePath($name) .'"
        loading="lazy"
    />';
}

echo '
</div>
</details>
';


