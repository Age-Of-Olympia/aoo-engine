<?php

namespace App\Service;

use Classes\Exchange;
use Classes\Player;
use Classes\View;

/** Rules specific to exchanges between characters. */
final class PlayerExchangeService
{
    public function assertContact(Player $first, Player $second): void
    {
        $firstCoords = $first->getCoords();
        $secondCoords = $second->getCoords();
        if ($firstCoords === null || $secondCoords === null || View::get_distance($firstCoords, $secondCoords) > 1) {
            throw new \InvalidArgumentException('Vous devez être au contact pour échanger.');
        }
    }

    public function canReceive(Exchange $exchange, Player $recipient): bool
    {
        $bag = new ContainerService();
        $incoming = $exchange->contentsOf((int) ($exchange->playerId === $recipient->id ? $exchange->targetId : $exchange->playerId));
        $newLines = count($incoming['exemplars']);
        foreach ($incoming['stacks'] as $row) {
            $newLines += (int) $bag->stackNeedsRoom((int) $recipient->id, (int) $row['item_id']);
        }
        $capacity = $bag->capacityOf((int) $recipient->id);

        return $capacity === null || $bag->lineCountOf((int) $recipient->id) + $newLines <= $capacity;
    }

    public function hasIncomingValidatedProposal(Player $recipient, Player $proposer): bool
    {
        foreach (Exchange::get_open_exchanges((int) $recipient->id) as $exchange) {
            if ($exchange->exchangeType === 'player'
                && (int) $exchange->playerId === (int) $proposer->id
                && (int) $exchange->targetId === (int) $recipient->id
                && $exchange->playerOk === 1
                && $exchange->is_in_progress()) {
                return true;
            }
        }

        return false;
    }

    public function cancelOpenExchangesAfterMove(Player $mover, object $destination): void
    {
        foreach (Exchange::get_open_exchanges((int) $mover->id) as $exchange) {
            if ($exchange->exchangeType !== 'player') {
                continue;
            }
            $other = \App\Factory\PlayerFactory::legacy((int) ($exchange->playerId === $mover->id ? $exchange->targetId : $exchange->playerId));
            $other->get_data();
            if (!in_array((string) ($other->data->player_type ?? ''), ['real', 'npc', 'tutorial'], true)) {
                continue;
            }
            $otherCoords = $other->getCoords();
            if ($otherCoords === null || View::get_distance($destination, $otherCoords) > 1) {
                $exchange->db->start_transaction('cancel_player_exchange_movement');
                try {
                    $this->cancel($exchange, $mover, $other);
                    $exchange->db->commit_transaction('cancel_player_exchange_movement');
                } catch (\Throwable $error) {
                    $exchange->db->rollback_transaction('cancel_player_exchange_movement');
                    throw $error;
                }
            }
        }
    }

    /** Return both parties' escrowed items to their own bags and close the deal. */
    public function cancel(Exchange $exchange, Player $first, Player $second): void
    {
        foreach ([$first, $second] as $owner) {
            $contents = $exchange->contentsOf((int) $owner->id);
            if ($contents['stacks'] !== [] || $contents['exemplars'] !== []) {
                $exchange->give_items($owner, $owner, true);
            }
        }
        $exchange->purge_items();
        $exchange->cancel_exchange();
    }
}
