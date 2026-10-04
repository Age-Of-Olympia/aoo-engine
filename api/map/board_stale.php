<?php
/**
 * Polled by the HUD (js/hud.js initBoardPolling): has something changed in
 * the player's field of view since their board was drawn? The flag is set by
 * View::refresh_players_svg_in_box and cleared when MainView draws the board.
 */

define('SESSION_READ_ONLY', true);
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

header('Content-Type: application/json; charset=utf-8');

$stale = (new \Classes\Db())->exe(
    'SELECT stale FROM board_views WHERE player_id = ?',
    array((int) $_SESSION['playerId'])
)->fetch_object();

echo json_encode(['stale' => (bool) ($stale->stale ?? false)]);
