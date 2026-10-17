<?php

namespace Tests\Player;

use App\Service\ItemInstanceService;
use Classes\Item;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Vente d'un objet INDIVIDUALISÉ : l'entiercement par localisation.
 *
 * Le bug d'origine ne se voyait pas. Depuis que la banque accepte les
 * exemplaires, le formulaire de vente les affichait ; les vendre
 * débitait la PILE du même objet quand le vendeur en avait une — son
 * arme usée restait au coffre pendant qu'une unité vierge partait au
 * marché, sans message. Ce qui est épinglé ici est donc moins « la
 * vente marche » que « le bon objet part, et un seul ».
 */
#[Group('items-baseline')]
class MarketInstanceBaselineTest extends LegacyPlayerFixtureTestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTableOrSkip('items_bids', 'instance_id');
    }

    /** Un exemplaire usé, dans le sac — l'état de départ d'une vente. */
    private function wornInstance(int $playerId, Item $item): int
    {
        $service = new ItemInstanceService();
        $instanceId = $service->promote($playerId, $item->id);
        $this->setRemainingLife($instanceId, 35);

        return $instanceId;
    }

    public function testSellingAnInstanceNeverTouchesTheStackOfTheSameItem(): void
    {
        $seller = $this->createRealPlayer('GmSeller');
        $bois = $this->itemOrSkip('bois');

        // Le cas qui produisait la perte silencieuse : une pile ET un
        // exemplaire du même objet catalogue, tous deux dans le sac.
        $bois->add_item($seller, 1);
        $instanceId = $this->wornInstance($seller->id, $bois);
        $bois->add_item($seller, 5);

        $service = new ItemInstanceService();
        $service->escrowForMarket($instanceId, $seller->id);

        $this->assertSame(5, $bois->get_n($seller, includeInstances: false),
            'la pile du sac est INTACTE — c\'est tout l\'objet du correctif');

        $this->assertSame(
            ItemInstanceService::LOCATION_MARKET,
            (string) $this->link->fetchOne(
                "SELECT IF(e.slot IN ('bank','market','exchange'), e.slot, 'inventory')
               FROM players e JOIN item_instances i ON i.entity_id = e.id WHERE i.id = ?",
                [$instanceId]
            )
        );
    }

    public function testAnInstanceOnSaleIsOutOfReachButStillOwned(): void
    {
        $seller = $this->createRealPlayer('GmSeller');
        $bois = $this->itemOrSkip('bois');
        $bois->add_item($seller, 1);

        $service = new ItemInstanceService();
        $instanceId = $this->wornInstance($seller->id, $bois);
        $service->escrowForMarket($instanceId, $seller->id);

        $this->assertSame([], $service->listForInventory($seller->id, false), 'hors inventaire');
        $this->assertSame([], $service->listForBank($seller->id), 'hors banque : il est au marché');
        $this->assertSame(0, $service->countInstances($seller->id, $bois->id), 'ne compte pas sous la main');
        $this->assertFalse($service->hasEquippableUnit($seller->id, $bois->id), 'non équipable');
        $this->assertSame(1, $service->countOwned($seller->id, $bois->id), 'mais toujours possédé');
    }

    public function testBuyingTransfersIdentityAndWearToTheBuyer(): void
    {
        $seller = $this->createRealPlayer('GmSeller');
        $buyer = $this->createRealPlayer('GmBuyer');
        $bois = $this->itemOrSkip('bois');
        $bois->add_item($seller, 1);

        $service = new ItemInstanceService();
        $instanceId = $this->wornInstance($seller->id, $bois);
        $before = $this->link->fetchAssociative('SELECT * FROM item_instances WHERE id = ?', [$instanceId]);

        $service->escrowForMarket($instanceId, $seller->id);
        $service->deliverEscrow($instanceId, $seller->id, $buyer->id, ItemInstanceService::LOCATION_MARKET, ItemInstanceService::LOCATION_INVENTORY);

        $link = $this->link->fetchAssociative(
            "SELECT e.holder_id AS player_id,
                     IF(e.slot IN ('bank','market','exchange'), e.slot, 'inventory') AS location
               FROM players e JOIN item_instances i ON i.entity_id = e.id WHERE i.id = ?",
            [$instanceId]
        );
        $this->assertSame($buyer->id, (int) $link['player_id'], 'l\'acheteur en est propriétaire');
        $this->assertSame(ItemInstanceService::LOCATION_INVENTORY, $link['location'], 'et le reçoit dans son sac');

        $after = $this->link->fetchAssociative('SELECT * FROM item_instances WHERE id = ?', [$instanceId]);
        $this->assertSame($before, $after, 'usure et identité inchangées : la ligne n\'a pas bougé');
    }

    public function testTwoSimultaneousBuyersCannotBothTakeTheSameInstance(): void
    {
        $seller = $this->createRealPlayer('GmSeller');
        $first = $this->createRealPlayer('GmBuyerA');
        $second = $this->createRealPlayer('GmBuyerB');
        $bois = $this->itemOrSkip('bois');
        $bois->add_item($seller, 1);

        $service = new ItemInstanceService();
        $instanceId = $this->wornInstance($seller->id, $bois);
        $service->escrowForMarket($instanceId, $seller->id);

        $service->deliverEscrow($instanceId, $seller->id, $first->id, ItemInstanceService::LOCATION_MARKET, ItemInstanceService::LOCATION_INVENTORY);

        // Le second arrive après : l'exemplaire n'est plus ni séquestré,
        // ni au vendeur. Il doit être refusé, pas livré une seconde fois.
        $this->expectException(\InvalidArgumentException::class);
        $service->deliverEscrow($instanceId, $seller->id, $second->id, ItemInstanceService::LOCATION_MARKET, ItemInstanceService::LOCATION_INVENTORY);
    }

    public function testCancellingAnOfferBringsTheInstanceBackToTheBag(): void
    {
        $seller = $this->createRealPlayer('GmSeller');
        $bois = $this->itemOrSkip('bois');
        $bois->add_item($seller, 1);

        $service = new ItemInstanceService();
        $instanceId = $this->wornInstance($seller->id, $bois);
        $service->escrowForMarket($instanceId, $seller->id);
        $service->releaseFromMarket($instanceId, $seller->id);

        $bag = $service->listForInventory($seller->id, false);
        $this->assertCount(1, $bag);
        $this->assertSame($instanceId, (int) $bag[0]['instance_id']);
        $this->assertSame(35, (int) $bag[0]['durability'], 'et il revient avec son usure');
    }

    public function testCancellingAnOfferIsRefusedWhenTheBagIsFull(): void
    {
        $seller = $this->createRealPlayer('GmSeller');
        $bois = $this->itemOrSkip('bois');
        $pierre = $this->itemOrSkip('pierre');
        $bois->add_item($seller, 1);

        $service = new ItemInstanceService();
        $instanceId = $this->wornInstance($seller->id, $bois);
        $service->escrowForMarket($instanceId, $seller->id);
        $pierre->add_item($seller, 1);

        // The bag's ceiling is the race capacity: one line, taken by the stone
        $race = (string) $this->link->fetchOne('SELECT race FROM players WHERE id = ?', [$seller->id]);
        $before = (int) $this->link->fetchOne('SELECT capacity FROM races WHERE name = ?', [$race]);
        $this->link->executeStatement('UPDATE races SET capacity = 1 WHERE name = ?', [$race]);
        $this->refreshRaceCatalog();
        try {
            $service->releaseFromMarket($instanceId, $seller->id);
            $this->fail('a full bag must refuse the exemplar');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Votre sac est plein.', $e->getMessage());
        } finally {
            $this->link->executeStatement('UPDATE races SET capacity = ? WHERE name = ?', [$before, $race]);
            $this->refreshRaceCatalog();
        }
        $this->assertSame([], $service->listForInventory($seller->id, false), 'still on sale');
    }

    public function testTheDatabaseItselfRefusesTwoOffersOnOneInstance(): void
    {
        $seller = $this->createRealPlayer('GmSeller');
        $bois = $this->itemOrSkip('bois');
        $bois->add_item($seller, 1);
        $instanceId = $this->wornInstance($seller->id, $bois);

        $insert = 'INSERT INTO items_bids (item_id, player_id, n, price, stock, instance_id) VALUES (?, ?, 1, 10, 1, ?)';
        $this->link->executeStatement($insert, [$bois->id, $seller->id, $instanceId]);

        try {
            // L'index UNIQUE est la garde de premier rang : une garde
            // applicative se contourne, un index non.
            $this->expectException(\Throwable::class);
            $this->link->executeStatement($insert, [$bois->id, $seller->id, $instanceId]);
        } finally {
            $this->link->executeStatement('DELETE FROM items_bids WHERE instance_id = ?', [$instanceId]);
        }
    }
}
