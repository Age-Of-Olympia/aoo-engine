<?php
use Classes\File;

echo '<details>';
echo '<summary style="cursor: pointer; font-weight: bold; margin: 10px 0;"><h3 style="display: inline;">Foregrounds (indestructibles, passables)</h3></summary>';

// Separate regular and unique foregrounds
$regularForegrounds = [];
$uniqueForegrounds = [];

foreach(File::scan_dir('img/foregrounds/', without:".png") as $e){
    $url = 'img/foregrounds/'. $e .'.png';

    if(!file_exists($url)){
        continue;
    }

    if(str_starts_with($e, 'unique_')){
        $uniqueForegrounds[] = ['name' => $e, 'url' => $url];
    } else {
        $regularForegrounds[] = ['name' => $e, 'url' => $url];
    }
}

// Display regular foregrounds
echo '<div>';
foreach($regularForegrounds as $fg){
    echo '<img
        class="map foregrounds select-name"
        data-type="foregrounds"
        data-name="'. $fg['name'] .'"
        src="'. $fg['url'] .'"
        width="50"
        loading="lazy"
    />';
}
echo '</div>';

// Display unique foregrounds in a subsection if any exist
if(!empty($uniqueForegrounds)){
    echo '<details style="margin-top: 10px;">';
    echo '<summary style="cursor: pointer; font-weight: bold; padding: 5px; background: rgba(0, 0, 0, 0.05); border-radius: 4px;"><h4 style="display: inline; font-size: 14px;">Uniques</h4></summary>';
    echo '<div style="margin-top: 5px;">';

    foreach($uniqueForegrounds as $fg){
        echo '<img
            class="map foregrounds select-name"
            data-type="foregrounds"
            data-name="'. $fg['name'] .'"
            src="'. $fg['url'] .'"
            width="50"
            loading="lazy"
        />';
    }

    echo '</div>';
    echo '</details>';
}

echo '</details>
';
