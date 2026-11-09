<?php
/**
 * Polled by the HUD (js/hud.js initBoardPolling): has something changed in
 * the player's field of view since their board was drawn? See BoardChanges.
 */

define('SESSION_READ_ONLY', true);
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

header('Content-Type: application/json; charset=utf-8');

$playerId = (int) $_SESSION['playerId'];

echo json_encode([
    'stale' => \App\Service\Map\BoardChanges::isStale($playerId),
    // Compared by the HUD with #hud-topbar[data-live] (TopBarView).
    'live' => \App\View\Hud\TopBarView::liveSignature($playerId),
]);
