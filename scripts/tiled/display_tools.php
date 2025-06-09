<?php


echo '<h3>Outils</h3>';

echo '
<div>
';

echo '
<img
    class="map dialog select-name"
    data-type="dialogs"
    data-params="dialog"
    data-name="question"
    src="img/dialogs/question.png"
    />
    
<img
    class="map eraser select-name"
    data-type="eraser"
    data-params="eraser"
    data-name="erase"
    src="img/tools/eraser.png"
    />
    
<img
    class="map harvest_mode select-name"
    data-type="harvest_mode"
    data-params="harvest_mode"
    data-name="harvest_mode"
    src="img/tools/scythe.png"
    />
    
<img
    class="map info select-name"
    data-type="infos"
    data-params="info"
    data-name="info"
    src="img/tools/glass.png"
    />    
';

echo '<div>Params: <input type="text" id="dialogs-params" /></div>';

echo '
</div>
';
