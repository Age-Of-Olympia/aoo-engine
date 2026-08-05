<?php

use App\Service\ContainerService;

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ExitError('Invalid request');
}

$POST_DATA = json_decode(file_get_contents('php://input'), true) ?: $_POST;

/* The ACTOR is the session; the service re-checks standing, closure,
 * household and reach on every gesture. */
$actorId = (int) $_SESSION['playerId'];
$containerId = (int) ($POST_DATA['containerId'] ?? 0);
$service = new ContainerService();

try {
    switch ((string) ($POST_DATA['action'] ?? '')) {
        case 'stack-deposit':
            $service->depositStack($containerId, $actorId, (int) ($POST_DATA['itemId'] ?? 0), (int) ($POST_DATA['n'] ?? 0));
            ExitSuccess(['message' => 'Déposé.']);
            break;
        case 'stack-withdraw':
            $service->withdrawStack($containerId, $actorId, (int) ($POST_DATA['itemId'] ?? 0), (int) ($POST_DATA['n'] ?? 0));
            ExitSuccess(['message' => 'Pris.']);
            break;
        case 'exemplar-deposit':
            $service->depositExemplar($containerId, $actorId, (int) ($POST_DATA['instanceId'] ?? 0));
            ExitSuccess(['message' => 'Déposé.']);
            break;
        case 'exemplar-withdraw':
            $service->withdrawExemplar($containerId, $actorId, (int) ($POST_DATA['instanceId'] ?? 0));
            ExitSuccess(['message' => 'Pris.']);
            break;
        case 'withdraw-all':
            $sweep = $service->withdrawAll($containerId, $actorId);
            if ($sweep['taken'] === []) {
                ExitError($sweep['full'] ? 'Votre sac est plein.' : 'Rien à prendre.');
            }
            $message = 'Pris : ' . implode(', ', $sweep['taken']) . '.';
            if ($sweep['full']) {
                $message .= ' Sac plein — le reste attend.';
            }
            ExitSuccess(['message' => $message]);
            break;
        case 'lock':
            $open = (int) ($POST_DATA['open'] ?? 0) === 1;
            $service->toggleOpen($containerId, $actorId, $open);
            ExitSuccess(['message' => $open ? 'Ouvert.' : 'Fermé.']);
            break;
        default:
            ExitError('action inconnue');
    }
} catch (\RuntimeException | \InvalidArgumentException $e) {
    ExitError($e->getMessage());
}
