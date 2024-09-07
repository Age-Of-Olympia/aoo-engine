<?php

echo '
<div id="tools">
';

$tools = array(
    'i'=>['i', "Italique"],
    'b'=>['b', "Gras"],
    'u'=>['u', "Souligné"],
    's'=>['s', "Barré"],
    '"'=>['quote', "Citation"],
    '🔗'=>['url', "Lien"],
    '🌄'=>['img', "Image"]
         );


foreach($tools as $k=>$e){


    echo '<div class="tool-button"><a href="#" title="'. $e[1] .'"><button data-tag="'. $e[0] .'">'. $k .'</button></a></div>';
}


echo '
</div>
';

?>
<script src="js/forum_tools.js"></script>
