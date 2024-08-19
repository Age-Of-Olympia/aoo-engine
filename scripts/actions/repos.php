<?php


echo '
Vous vous reposez.
';


// fatigue
if($player->data->fatigue){


    $player->put_fat(-FAT_PER_REST);

    $fat = ($player->data->fatigue > FAT_PER_REST) ? FAT_PER_REST : $player->data->fatigue;

    // echo '<div class="action-details">'. $fat .' Fatigues enlevées.</div>';

    echo $fat .' Fatigues enlevées. ';
}


$sql = '
SELECT COUNT(*) AS n
FROM players_effects
WHERE
endTime <= '. time() .'
AND
endTime != 0
AND
player_id = '. $player->id
;

$db = new Db();

$count = $db->get_count($sql);


if($count){

    $player->purge_effects();

    // echo '<div class="action-details">'. $count .' effets terminés.</div>';

    $effects = '';

    foreach($player->get_effects() as $e){


        if(in_array($e, EFFECTS_HIDDEN)){

            continue;
        }

        $effects .= ' <a href="infos.php?targetId='. $player->id .'"><span class="ra '. EFFECTS_RA_FONT[$e] .'"></span></a>';
    }

    echo '<script>$(".effects").html("'. $effects .'");</script>';


    echo $count .' effets terminés.';
}



// special : dot not add -1 A with $payer->put_bonus() cause it add 1 Fat

$bonus = array();

$values = array(
    'player_id'=>$player->id,
    'name'=>'a',
    'n'=>-1
);

$sql = '
INSERT INTO
players_bonus
(`player_id`,`name`,`n`)
VALUES(?,?,?)
ON DUPLICATE KEY UPDATE
n = n + VALUES(n);
';

$db->exe($sql, array($player->id, 'a', -1));
