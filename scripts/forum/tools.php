<?php

echo '
<div id="tools">
    <div class="portrait">
        <img src="'. $player->data->portrait .'" />
    </div>
    <div id="tools-button">
';

$tools = array(
    'i'=>['i', "Italique"],
    'b'=>['b', "Gras"],
    'u'=>['u', "Souligné"],
    'c'=>['center', "Centré"],
    's'=>['s', "Barré"],
    '"'=>['quote', "Citation"],
    '🔗'=>['url', "Lien"],
    '🌄'=>['img', "Image"],
    '📺'=>['youtube', "Youtube"]
         );


foreach($tools as $k=>$e){


    echo '<div class="tool-button"><a href="#" title="'. $e[1] .'"><button data-tag="'. $e[0] .'">'. $k .'</button></a></div>';
}


echo '<div class="tool-button"><a href="https://age-of-olympia.net/wiki/doku.php?id=forum:bbcode" title="Aide"><button>?</button></a></div>';

echo '
    </div>
</div>
';

?>
<script src="js/forum_tools.js?v=20260715"></script>
