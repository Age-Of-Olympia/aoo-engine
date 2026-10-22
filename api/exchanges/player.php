<?php

use App\Factory\PlayerFactory;
use App\Service\PlayerExchangeService;
use Classes\Exchange;
use Classes\Log;

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ExitError('Invalid request');
}

$player = PlayerFactory::active();
$player->get_data();
$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$rules = new PlayerExchangeService();
$target = PlayerFactory::legacy((int) ($_GET['targetId'] ?? 0));
$target->get_data();
try {
    $rules->assertContact($player, $target);
} catch (InvalidArgumentException $error) {
    ExitError($error->getMessage());
}
if ((int) $target->id === (int) $player->id
    || !in_array((string) ($target->data->player_type ?? ''), ['real', 'npc', 'tutorial'], true)) {
    ExitError('Vous ne pouvez pas échanger avec vous-même.');
}

if (($payload['action'] ?? '') === 'create') {
    $exchange = new Exchange();
    $exchange->create((int) $player->id, (int) $target->id, 'player');
    ExitSuccess((int) $exchange->id);
}

$exchange = new Exchange((int) ($payload['id'] ?? 0));
$exchange->get_base_data();
$exchange->get_items_data();
if (!$exchange->is_in_progress()
    || $exchange->exchangeType !== 'player'
    || !in_array((int) $player->id, [(int) $exchange->playerId, (int) $exchange->targetId], true)
    || !in_array((int) $target->id, [(int) $exchange->playerId, (int) $exchange->targetId], true)) {
    ExitError('Cet échange n’est plus disponible.');
}

$other = PlayerFactory::legacy((int) ($exchange->playerId === $player->id ? $exchange->targetId : $exchange->playerId));
$other->get_data();
try {
    $exchange->db->start_transaction('player_exchange');
    switch ($payload['action'] ?? '') {
        case 'stack-give':
            $exchange->give($player, (int) ($payload['n'] ?? 0), (int) ($payload['itemId'] ?? 0));
            break;
        case 'exemplar-give':
            $exchange->give($player, 1, null, (int) ($payload['instanceId'] ?? 0));
            break;
        case 'stack-take':
            $exchange->takeBack($player, (int) ($payload['n'] ?? 0), (int) ($payload['itemId'] ?? 0));
            break;
        case 'exemplar-take':
            $exchange->takeBack($player, 1, null, (int) ($payload['instanceId'] ?? 0));
            break;
        case 'refuse':
        case 'cancel':
            $rules->cancel($exchange, $player, $other);
            break;
        case 'accept':
            $mine = $exchange->contentsOf((int) $player->id);
            $theirs = $exchange->contentsOf((int) $other->id);
            if ($mine['stacks'] === [] && $mine['exemplars'] === [] && $theirs['stacks'] === [] && $theirs['exemplars'] === []) {
                throw new InvalidArgumentException('Ajoutez au moins un objet avant de valider.');
            }
            if (empty($payload['lastmodification']) || (int) $payload['lastmodification'] !== (int) $exchange->updateTime) {
                throw new InvalidArgumentException('L’échange a été modifié. Vérifiez les objets avant de valider.');
            }
            if (!$rules->canReceive($exchange, $player)) {
                throw new InvalidArgumentException('Cet échange dépasse la taille du sac');
            }
            $exchange->accept_exchange((int) $player->id === (int) $exchange->targetId);
            if ($exchange->playerOk === 1 && $exchange->targetOk === 1) {
                if ($rules->canReceive($exchange, $player) && $rules->canReceive($exchange, $other)) {
                    $initiator = (int) $exchange->playerId === (int) $player->id ? $player : $other;
                    $recipient = (int) $exchange->targetId === (int) $player->id ? $player : $other;
                    $fromInitiator = $exchange->give_items($initiator, $recipient, true);
                    $fromRecipient = $exchange->give_items($recipient, $initiator, true);
                    $time = time();
                    $initiator->getCoords();
                    $recipient->getCoords();
                    Log::put($initiator, $recipient, $initiator->data->name . ' a donné à ' . $recipient->data->name . ' : ' . $fromInitiator . '; ' . $recipient->data->name . ' a donné : ' . $fromRecipient . '.', 'player_exchange', '', $time);
                    Log::put($recipient, $initiator, $recipient->data->name . ' a reçu de ' . $initiator->data->name . ' : ' . $fromInitiator . '; ' . $recipient->data->name . ' a donné : ' . $fromRecipient . '.', 'player_exchange_received', '', $time);
                    $exchange->purge_items();
                }
            }
            break;
        default:
            throw new InvalidArgumentException('Action invalide.');
    }
    $exchange->db->commit_transaction('player_exchange');
    ExitSuccess(['message' => '']);
} catch (Throwable $error) {
    $exchange->db->rollback_transaction('player_exchange');
    ExitError($error instanceof InvalidArgumentException ? $error->getMessage() : 'Impossible de modifier cet échange.');
}
