<?php
use Classes\File;

echo '<h3>Déclencheurs (invisibles)</h3>';

echo '
<div>
';

foreach(File::scan_dir('img/triggers/', without:".png") as $e){


    $params = '';

    if($e == 'exit'){

        $params = 'sw';
    }
    elseif($e == 'tp'){

        $params = 'x,y,z,plan';
    }
    elseif($e == 'need'){

        $params = 'item:name:n,spell:spell_name';
    }
    elseif($e == 'enter'){

        $params = 'ne';
    }


    echo '<img
        class="map trigger select-name"
        data-type="triggers"
        data-params="'. $params .'"
        data-name="'. $e .'"
        src="img/triggers/'. $e .'.png"
    />';
}


echo '<div>Params: <input type="text" id="triggers-params" /></div>';


echo '
</div>
';

