<?php

echo '<textarea style="width: 100vw; height: 50vw;">';

$effects = EFFECTS_TXT;

sort($effects);

foreach($effects as $e){

    $txt = explode('<br />', $e);

echo '
===== '. $txt[0] .' =====
'. $txt[1] .'';

}


echo '</textarea>';
