<?php
/**
 * Reschedule the player's next turn.
 *
 * POST nextTurn=Y-m-d\TH:i (datetime-local format, server timezone)
 *
 * The new time must lie inside the window computed by
 * TurnScheduleService::rescheduleWindow(): between the currently scheduled
 * turn and the potential following one (based on the speed carac).
 */

header('Content-Type: application/json; charset=utf-8');

require_once(__DIR__ . '/../../config.php');

use App\Service\TurnScheduleService;
use Classes\Player;

if (empty($_SESSION['playerId'])) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'error' => 'Not authenticated'));
    exit;
}

$raw = $_POST['nextTurn'] ?? '';
$date = DateTime::createFromFormat('Y-m-d\TH:i', $raw);

if ($raw === '' || $date === false) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'error' => 'Format de date invalide.'));
    exit;
}

// Rescheduling always applies to the real player: tutorial players live on
// their own plan and never refresh turns.
$player = new Player($_SESSION['playerId']);
$player->get_data(false);
$player->get_caracs();

// one reschedule per turn cycle — the flag is cleared on turn refresh
if (!empty($player->data->nextTurnRescheduled)) {
    http_response_code(400);
    echo json_encode(array(
        'success' => false,
        'error' => 'Vous avez déjà décalé votre prochain tour pour ce cycle.',
    ));
    exit;
}

$candidate = $date->getTimestamp();
$window = TurnScheduleService::rescheduleWindow($player->data->nextTurnTime, $player->caracs->spd);

if (!TurnScheduleService::isWithinRescheduleWindow($candidate, $player->data->nextTurnTime, $player->caracs->spd)) {
    http_response_code(400);
    echo json_encode(array(
        'success' => false,
        'error' => 'Heure hors de la fenêtre autorisée (du ' . date('d/m/Y H:i', $window['min'])
            . ' au ' . date('d/m/Y H:i', $window['max']) . ').',
        'min' => $window['min'],
        'max' => $window['max'],
    ));
    exit;
}

(new TurnScheduleService())->reschedule($player->id, $candidate);

$player->refresh_data();

echo json_encode(array(
    'success' => true,
    'nextTurnTime' => $candidate,
    'formatted' => date('d/m/Y à H:i', $candidate),
));
