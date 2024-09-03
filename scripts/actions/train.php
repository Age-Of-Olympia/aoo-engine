<?php


// forbidden training
if($target->have_option('noTrain')){

    exit('<font color="red">Ce personnage n\'autorise pas les entraînements.</font>');
}


// training limitation
if($target->data->fatigue >= FAT_EVERY){

    exit('<font color="red">Ce personnage est trop fatigué pour s\'entraîner.</font>');
}

if($player->data->fatigue >= FAT_EVERY){

    exit('<font color="red">Vous êtes trop fatigué pour vous entraîner.</font>');
}

$target->put_fat(1);


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

// Vous vous entraînez avec '. $target->data->name .' (+'. $playerXp .'Xp).

echo '


<div class="action-details">
    '. $player->data->name .' (rang '. $playerRank .') +'. $playerXp .'Xp<br />
    '. $target->data->name .' (rang '. $targetRank .') +'. $targetXp .'Xp
</div>
';
