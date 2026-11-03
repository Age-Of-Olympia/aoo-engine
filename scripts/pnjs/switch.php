<?php

use App\Service\ImpersonationService;
use App\Service\PlayerPnjService;

/*
 * Character switch posted by js/pnjs.js. Included by pnjs.php BEFORE the
 * page envelope, so the answer is an empty body on success and the
 * refusal text otherwise.
 */

$mainId = (int) $_SESSION['mainPlayerId'];
$target = (int) $_POST['switch'];

if (!in_array($target, (new PlayerPnjService())->controllableIds($mainId), true)) {
    exit('error pnj');
}

/* driveAs refuses to go from one driven character to another: go back to
 * the main character first, the target is already checked above. */
try {
    $impersonation = new ImpersonationService();
    if ((int) $_SESSION['playerId'] !== $mainId) {
        $impersonation->release();
    }
    $impersonation->driveAs($target);
} catch (\RuntimeException $e) {
    exit($e->getMessage());
}

exit();
