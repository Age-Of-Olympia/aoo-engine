<?php


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


$player->purge_effects();

echo '
Vous vous reposez.

<div class="action-details">'. $count .' effets terminés.</div>
';
