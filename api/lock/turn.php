<?php

use App\Service\LockService;

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ExitError('Invalid request');
}

$POST_DATA = json_decode(file_get_contents('php://input'), true) ?: $_POST;

/* The ACTOR is the session; LockService re-checks the household, the
 * rank and a jammed lock. No reach: the faction panel turns locks from
 * afar on purpose. */
$open = (int) ($POST_DATA['open'] ?? 0) === 1;

try {
    (new LockService())->toggleOpen((int) ($POST_DATA['targetId'] ?? 0), (int) $_SESSION['playerId'], $open);
    ExitSuccess(['message' => $open ? 'Ouvert.' : 'Fermé.']);
} catch (\RuntimeException $e) {
    ExitError($e->getMessage());
}
