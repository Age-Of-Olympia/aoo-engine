<?php
use App\Service\Map\TriggerRequirements;

/* La règle « ce qu'il faut porter » vit dans TriggerRequirements : le
   téléporteur l'applique aussi, en dernier paramètre. */
if (!TriggerRequirements::met($player, (string) $params)) {

    echo '<script>alert("'. TriggerRequirements::REFUSAL .'");</script>';

    exit();
}
