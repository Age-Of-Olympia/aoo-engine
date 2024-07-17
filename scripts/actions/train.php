<?php

$playerRank = $player->data->rank;
$targetRank = $target->data->rank;


if($playerRank == $targetRank){

    $playerXp = 2;
    $targetXp = 2;
}
elseif($playerRank > $targetRank){

    $playerXp = 1;
    $targetXp = 3;
}
elseif($playerRank < $targetRank){

    $playerXp = 3;
    $targetXp = 1;
}


$player->put_xp($playerXp);
$target->put_xp($targetXp);


echo '
Vous vous entraînez avec '. $target->data->name .' (+'. $playerXp .'Xp).

<div class="action-details">
    '. $player->data->name .' (rang '. $playerRank .') +'. $playerXp .'Xp<br />
    '. $target->data->name .' (rang '. $targetRank .') +'. $targetXp .'Xp
</div>
';
