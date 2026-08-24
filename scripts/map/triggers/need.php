<?php
use App\Service\Map\TriggerRequirements;

/* Shared with tp.php, whose fifth parameter holds the same syntax. */
if (!TriggerRequirements::met($player, (string) $params)) {

    echo '<script>alert("'. TriggerRequirements::REFUSAL .'");</script>';

    exit();
}
