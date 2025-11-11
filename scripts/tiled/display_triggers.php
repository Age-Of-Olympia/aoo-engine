<?php
use Classes\File;

echo '<details>';
echo '<summary style="cursor: pointer; font-weight: bold; margin: 10px 0;"><h3 style="display: inline;">Déclencheurs (invisibles)</h3></summary>';

echo '
<div>
';

foreach(File::scan_dir('img/triggers/', without:".png") as $e){


    $params = '';


    if($e == 'tp'){

        $params = 'x,y,z,plan';
    }
    elseif($e == 'need'){

        $params = 'item:name:n,spell:spell_name';
    }
    elseif($e == 'grow'){

        $params = 'adonis';
    }



    echo '<img
        class="map trigger select-name"
        data-type="triggers"
        data-params="'. $params .'"
        data-name="'. $e .'"
        src="img/triggers/'. $e .'.png"
        loading="lazy"
    />';
}


echo '<div>Params: <input type="text" id="triggers-params" /></div>';


echo '
</div>
</details>
';

