<?php

$ui = new Ui($target->data->name .' (réputation)');

echo '
<style>
.rewards-wrapper {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    max-width: 75%;
}
.rewards {
    display: flex;
    justify-content: center;
}
</style>
<div><a href="infos.php?targetId='. $target->id .' &reputation"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';
echo '
<h1>
   Collection de '.$target->data->name.'
</h1>
';


$sql = '
SELECT a.img FROM
    players_forum_rewards AS a
WHERE
    a.to_player_id = ? or a.from_player_id = ? 
group by a.img
order by a.img';

$db = new Db();

$result = $db->exe($sql, array($target->id,$target->id));

if(!$result->num_rows){

    // echo 'Aucune récompense.';
}
else{
    echo '<div class="rewards"><div class="rewards-wrapper">';
    while( $row = $result->fetch_assoc() ){
      echo '<img src="'. $row['img'] .'" class="img-reward" />';
    }
    echo '</div></div>';
}
