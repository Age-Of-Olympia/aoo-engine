<?php

$pf = rand(1,3);

$player->put_pf($pf);

echo '
Vous priez et gagnez '. $pf .' Points de Foi (total '. $player->data->pf .'Pf).

<div class="action-details">1d3 = '. $pf .'</div>
';
