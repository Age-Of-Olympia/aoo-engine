<?php

use App\Factory\PlayerFactory;
use App\Service\PlayerCaracsService;
use Classes\WarSchool;

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

/*
 * Buy back a carac rank: the invested Pi come back, gold pays for the
 * service. The disabled state of the table's button is a comfort only —
 * the guards below decide, and a forged request walks the same path.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ExitError(INVALID_REQ);
}

$POST_DATA = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$caracKey = (string) ($POST_DATA['carac'] ?? '');

/* Neither Ae nor Spd is bought: they have no rank to give back. */
if (!isset(CARACS[$caracKey]) || $caracKey === 'ae' || $caracKey === 'spd') {
    ExitError('Caractéristique invalide.');
}

$player = PlayerFactory::active();
$player->get_data();
$player->get_row();
$player->get_caracs();

/* No reassigning from home: same access guard as the six teaching
 * counters (school open, within reach, compatible states). */
$trainer = PlayerFactory::legacy((int) ($POST_DATA['targetId'] ?? 0));

$accessError = WarSchool::checkAccess($player, $trainer);
if ($accessError !== null) {
    ExitError($accessError);
}

$ranks = (int) ($player->upgrades->$caracKey ?? 0);
if ($ranks < 1) {
    ExitError('Vous ne pouvez pas descendre plus bas.');
}

$cost = (new PlayerCaracsService())->returnCost($caracKey, $ranks - 1);

/* The attempt pays, the arrival rewards: the gold leaves first, in one
 * write that says whether it happened, and only then does the rank go. */
if (!$player->spendGold($cost)) {
    ExitError('Or insuffisant !');
}

$player->remove_upgrade($caracKey, 1);

ExitSuccess(['message' => CARACS[$caracKey] . ' réassignée pour ' . $cost . ' Po.']);
