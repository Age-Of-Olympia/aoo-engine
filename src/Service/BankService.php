<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Factory\PlayerFactory;
use Classes\Item;
use Doctrine\DBAL\Connection;

/**
 * Le coffre en banque d'un joueur — le pendant de ContainerService pour
 * l'écran partagé ExchangePanesView : mêmes volets, mêmes gestes, mais
 * le rangement vit dans les tables de la banque (piles
 * `players_items_bank`, exemplaires `slot = 'bank'` via
 * ItemInstanceService) et il est PERSONNEL : chacun son coffre, où que
 * soit le guichet.
 *
 * Les primitives restent les historiques (Item::add_item(bank:),
 * storeInBank / withdrawFromBank) — ce service ne fait qu'aligner
 * leurs gardes sur le motif du contenant : refus en clair, jamais de
 * moitié de geste.
 */
class BankService
{
    private Connection $conn;

    public function __construct()
    {
        $this->conn = EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * Ce que le coffre détient — même double lecture que le sac
     * (ContainerService::contentsOf), mêmes clés pour les mêmes libellés.
     *
     * @return array{stacks: array<int, array<string, mixed>>, exemplars: array<int, array<string, mixed>>}
     */
    public function contentsOf(int $playerId): array
    {
        $stacks = $this->conn->fetchAllAssociative(
            'SELECT pib.item_id, it.name, pib.n
               FROM players_items_bank pib
               JOIN items it ON it.id = pib.item_id
              WHERE pib.player_id = ? AND pib.n > 0
              ORDER BY it.name',
            [$playerId]
        );

        $exemplars = $this->conn->fetchAllAssociative(
            "SELECT it.name, i.item_id, i.id AS instance_id, i.custom_name, e.id AS entity_id
               FROM players e
               JOIN item_instances i ON i.entity_id = e.id
               JOIN items it ON it.id = i.item_id
              WHERE e.holder_id = ? AND e.slot = 'bank' AND i.destroyed = 0
              ORDER BY it.name, i.id",
            [$playerId]
        );

        return ['stacks' => $stacks, 'exemplars' => $exemplars];
    }

    /** @throws \RuntimeException refus en clair */
    public function depositStack(int $playerId, int $itemId, int $n): void
    {
        $player = PlayerFactory::legacy($playerId);
        $item = new Item($itemId);

        if (!$item->is_bankable()) {
            throw new \RuntimeException('Cet objet est refusé en banque.');
        }

        /* Pile uniquement : le dépôt décrémente la pile — compter les
         * exemplaires gonflerait la garde et ferait échouer le débit
         * avec un message trompeur. */
        if ($n < 1 || $n > $item->get_n($player, includeInstances: false)) {
            throw new \RuntimeException('Quantité invalide.');
        }

        if (!$item->add_item($player, -$n)) {
            throw new \RuntimeException('Le retrait du sac a échoué.');
        }
        $item->add_item($player, $n, bank: true);
    }

    /** @throws \RuntimeException refus en clair */
    public function withdrawStack(int $playerId, int $itemId, int $n): void
    {
        $player = PlayerFactory::legacy($playerId);
        $item = new Item($itemId);

        if ($n < 1 || $n > $item->get_n($player, bank: true)) {
            throw new \RuntimeException('Quantité invalide.');
        }

        /* La règle des lignes du sac, comme au coffre et au ramassage —
         * AVANT le débit, sinon les unités seraient détruites. */
        $capacity = new ContainerService();
        if ($capacity->stackNeedsRoom($playerId, $itemId) && !$capacity->hasRoomForALine($playerId)) {
            throw new \RuntimeException('Votre sac est plein.');
        }

        if (!$item->add_item($player, -$n, bank: true)) {
            throw new \RuntimeException('Le retrait du coffre a échoué.');
        }
        $item->add_item($player, $n);
    }

    /** @throws \RuntimeException|\InvalidArgumentException refus en clair */
    public function depositExemplar(int $playerId, int $instanceId): void
    {
        $itemId = (int) $this->conn->fetchOne(
            'SELECT item_id FROM item_instances WHERE id = ?',
            [$instanceId]
        );
        if ($itemId > 0 && !(new Item($itemId))->is_bankable()) {
            throw new \RuntimeException('Cet objet est refusé en banque.');
        }

        (new ItemInstanceService())->storeInBank($instanceId, $playerId);
    }

    /** @throws \InvalidArgumentException refus en clair (sac plein compris) */
    public function withdrawExemplar(int $playerId, int $instanceId): void
    {
        (new ItemInstanceService())->withdrawFromBank($instanceId, $playerId);
    }
}
