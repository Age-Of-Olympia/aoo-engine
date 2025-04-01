<?php

if($target->haveEffect('martyr')){


    echo '<div><font color="red">'. $target->data->name .' réduit d\'un tiers les dégâts de votre attaque grâce au sort Martyr.</font></div>';

    $totalDamages = floor($totalDamages * 2 / 3);

    $target->put_pf($totalDamages);
}
