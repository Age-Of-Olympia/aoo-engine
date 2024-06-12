<?php

$playerRank = $player->row->rank;
$targetRank = $target->row->rank;


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

echo '
Vous vous entraînez avec '. $target->row->name .'.

<div class="action-details">
    '. $player->row->name .' (rang '. $playerRank .') +'. $playerXp .'Xp<br />
    '. $target->row->name .' (rang '. $targetRank .') +'. $targetXp .'Xp
</div>
';
