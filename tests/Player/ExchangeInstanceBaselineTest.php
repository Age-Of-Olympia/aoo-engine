<?php

namespace Tests\Player;

use App\Service\ItemInstanceService;
use Classes\Exchange;
use Classes\Item;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Échange d'un objet INDIVIDUALISÉ entre deux joueurs.
 *
 * Même entiercement par localisation que le marché, mais deux défauts
 * que le fongible masquait ont dû tomber d'abord : la table des lignes
 * d'échange n'avait AUCUNE clé primaire — retirer une ligne emportait
 * toutes ses jumelles — et ses lignes n'étaient JAMAIS supprimées après
 * règlement, ce qui rend indécidable la légitimité d'un séquestre.
 */
#[Group('items-baseline')]
class ExchangeInstanceBaselineTest extends LegacyPlayerFixtureTestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTableOrSkip('players_items_exchanges', 'instance_id');
    }

    /** Un exemplaire usé, dans le sac — l'état de départ. */
    private function wornInstance(int $playerId, Item $item, int $durability = 35): int
    {
        $service = new ItemInstanceService();
        $instanceId = $service->promote($playerId, $item->id);
        /* Plus de maximum par exemplaire — il vient du type. La consigne
         * devient donc « ce qu'il lui reste », exprimé sur ce maximum. */
        $this->setRemainingLife($instanceId, $durability);

        return $instanceId;
    }

    private function openExchange(int $playerId, int $targetId): Exchange
    {
        $exchange = new Exchange();
        $exchange->create($playerId, $targetId);
        $exchange->get_base_data();

        return $exchange;
    }

    public function testAnInstanceInPlayLeavesTheBagWithoutTouchingAnyStack(): void
    {
        $offering = $this->createRealPlayer('GmOffer');
        $target = $this->createRealPlayer('GmTarget');
        $bois = $this->itemOrSkip('bois');

        $bois->add_item($offering, 1);
        $instanceId = $this->wornInstance($offering->id, $bois);
        $bois->add_item($offering, 4);

        $service = new ItemInstanceService();
        $service->escrowForExchange($instanceId, $offering->id);

        $exchange = $this->openExchange($offering->id, $target->id);
        $exchange->add_item_to_exchange($bois->id, 1, $offering->id, $instanceId);

        $this->assertSame(4, $bois->get_n($offering, includeInstances: false), 'la pile du sac est intacte');

        $this->assertSame(
            ItemInstanceService::LOCATION_EXCHANGE,
            (string) $this->link->fetchOne(
                "SELECT IF(e.slot IN ('bank','market','exchange'), e.slot, 'inventory')
               FROM players e JOIN item_instances i ON i.entity_id = e.id WHERE i.id = ?",
                [$instanceId]
            )
        );
        $this->assertSame([], $service->listForInventory($offering->id, false), 'hors du sac tant qu\'il est en jeu');
        $this->assertFalse($service->isInstanceEquippable($offering->id, $bois->id, $instanceId), 'et non équipable');
    }

    public function testSettlingTransfersIdentityAndClearsTheLines(): void
    {
        $offering = $this->createRealPlayer('GmOffer');
        $target = $this->createRealPlayer('GmTarget');
        $bois = $this->itemOrSkip('bois');
        $bois->add_item($offering, 1);

        $service = new ItemInstanceService();
        $instanceId = $this->wornInstance($offering->id, $bois);
        $before = $this->link->fetchAssociative('SELECT * FROM item_instances WHERE id = ?', [$instanceId]);
        $service->escrowForExchange($instanceId, $offering->id);

        $exchange = $this->openExchange($offering->id, $target->id);
        $exchange->add_item_to_exchange($bois->id, 1, $offering->id, $instanceId);
        $exchange->get_items_data();

        $offering->get_data();
        $target->get_data();
        $recap = $exchange->give_items(from_player: $offering, to_player: $target);
        $exchange->purge_items();

        $link = $this->link->fetchAssociative(
            "SELECT e.holder_id AS player_id,
                     IF(e.slot IN ('bank','market','exchange'), e.slot, 'inventory') AS location
               FROM players e JOIN item_instances i ON i.entity_id = e.id WHERE i.id = ?",
            [$instanceId]
        );
        $this->assertSame($target->id, (int) $link['player_id'], 'le destinataire en est propriétaire');
        $this->assertSame(ItemInstanceService::LOCATION_BANK, $link['location'], 'et le reçoit en banque');

        $after = $this->link->fetchAssociative('SELECT * FROM item_instances WHERE id = ?', [$instanceId]);
        $this->assertSame($before, $after, 'usure et identité inchangées');

        $this->assertStringContainsString('Durabilité 35/100', $recap, 'le journal nomme CE qui est parti');

        $this->assertSame(0, (int) $this->link->fetchOne(
            'SELECT COUNT(*) FROM players_items_exchanges WHERE exchange_id = ?',
            [$exchange->id]
        ), 'les lignes disparaissent avec l\'échange réglé');
    }

    public function testTwoInstancesOfTheSameItemAreTwoSeparableLines(): void
    {
        $offering = $this->createRealPlayer('GmOffer');
        $target = $this->createRealPlayer('GmTarget');
        $bois = $this->itemOrSkip('bois');
        $bois->add_item($offering, 2);

        $service = new ItemInstanceService();
        $first = $this->wornInstance($offering->id, $bois, 5);
        $second = $this->wornInstance($offering->id, $bois, 12);
        $service->escrowForExchange($first, $offering->id);
        $service->escrowForExchange($second, $offering->id);

        $exchange = $this->openExchange($offering->id, $target->id);
        $exchange->add_item_to_exchange($bois->id, 1, $offering->id, $first);
        $exchange->add_item_to_exchange($bois->id, 1, $offering->id, $second);
        $exchange->get_items_data();

        $this->assertCount(2, $exchange->items, 'deux lignes distinctes, pas une ligne « x2 »');

        // Retirer LA ligne visée — c'est ce que la clé primaire permet.
        $lineOfFirst = null;
        foreach ($exchange->items as $line) {
            if ((int) $line->instance_id === $first) {
                $lineOfFirst = (int) $line->id;
            }
        }
        $this->assertNotNull($lineOfFirst);
        $exchange->remove_item_line($lineOfFirst);

        $remaining = (int) $this->link->fetchOne(
            'SELECT COUNT(*) FROM players_items_exchanges WHERE exchange_id = ?',
            [$exchange->id]
        );
        $this->assertSame(1, $remaining, 'la jumelle est restée');

        $this->assertSame($second, (int) $this->link->fetchOne(
            'SELECT instance_id FROM players_items_exchanges WHERE exchange_id = ?',
            [$exchange->id]
        ), 'et c\'est bien la bonne qui reste');
    }

    public function testWithdrawingFromTheExchangeBringsTheInstanceBackToTheBag(): void
    {
        $offering = $this->createRealPlayer('GmOffer');
        $target = $this->createRealPlayer('GmTarget');
        $bois = $this->itemOrSkip('bois');
        $bois->add_item($offering, 1);

        $service = new ItemInstanceService();
        $instanceId = $this->wornInstance($offering->id, $bois);
        $service->escrowForExchange($instanceId, $offering->id);
        $service->releaseFromExchange($instanceId, $offering->id);

        $bag = $service->listForInventory($offering->id, false);
        $this->assertCount(1, $bag);
        $this->assertSame(35, (int) $bag[0]['durability'], 'avec son usure');
    }

    public function testTheDatabaseRefusesOneInstanceInTwoExchanges(): void
    {
        $offering = $this->createRealPlayer('GmOffer');
        $target = $this->createRealPlayer('GmTarget');
        $bois = $this->itemOrSkip('bois');
        $bois->add_item($offering, 1);
        $instanceId = $this->wornInstance($offering->id, $bois);

        $first = $this->openExchange($offering->id, $target->id);
        $second = $this->openExchange($offering->id, $target->id);

        $first->add_item_to_exchange($bois->id, 1, $offering->id, $instanceId);

        // L'index UNIQUE tient même si la garde applicative est
        // contournée : un exemplaire ne peut être engagé qu'une fois.
        $this->expectException(\Throwable::class);
        $second->add_item_to_exchange($bois->id, 1, $offering->id, $instanceId);
    }

    public function testGiveAndTakeBackMoveBetweenTheBagAndTheExchange(): void
    {
        $offering = $this->createRealPlayer('GmGive');
        $target = $this->createRealPlayer('GmTake');
        $bois = $this->itemOrSkip('bois');
        $bois->add_item($offering, 1);
        $instanceId = $this->wornInstance($offering->id, $bois);
        $bois->add_item($offering, 10);
        $bagStack = fn (): int => $bois->get_n($offering, includeInstances: false);
        $exchange = $this->openExchange($offering->id, $target->id);
        $reload = function () use ($exchange): Exchange {
            $exchange->items = [];
            $exchange->get_items_data();

            return $exchange;
        };

        // Two gifts of a pile end up on one line
        $reload()->give($offering, 4, $bois->id);
        $reload()->give($offering, 3, $bois->id);
        $this->assertSame([7], array_map(fn ($r) => (int) $r['n'], $reload()->contentsOf($offering->id)['stacks']));
        $this->assertSame(3, $bagStack());

        // Taking part back leaves the rest in the exchange
        $reload()->takeBack($offering, 5, $bois->id);
        $this->assertSame([2], array_map(fn ($r) => (int) $r['n'], $reload()->contentsOf($offering->id)['stacks']));
        $this->assertSame(8, $bagStack());

        // An exemplar goes in and comes back by itself
        $reload()->give($offering, 1, null, $instanceId);
        $this->assertCount(1, $reload()->contentsOf($offering->id)['exemplars']);
        $reload()->takeBack($offering, 1, null, $instanceId);
        $this->assertSame([], $reload()->contentsOf($offering->id)['exemplars']);
        $this->assertSame('', (string) $this->link->fetchOne(
            'SELECT e.slot FROM players e JOIN item_instances i ON i.entity_id = e.id WHERE i.id = ?',
            [$instanceId]
        ));

        // More than the bag holds: refused, nothing moves
        try {
            $reload()->give($offering, 99, $bois->id);
            $this->fail('giving more than the bag holds must be refused');
        } catch (\InvalidArgumentException) {
        }
        $this->assertSame(8, $bagStack());
    }

    public function testCancelGivesTheCancellerBackInTheBagAndTheOtherInTheBank(): void
    {
        $canceller = $this->createRealPlayer('GmCancel');
        $other = $this->createRealPlayer('GmOther');
        $bois = $this->itemOrSkip('bois');
        $pierre = $this->itemOrSkip('pierre');
        $bois->add_item($canceller, 5);
        $pierre->add_item($other, 2);

        $exchange = $this->openExchange($canceller->id, $other->id);
        $exchange->get_items_data();
        $exchange->give($canceller, 5, $bois->id);
        $exchange->items = [];
        $exchange->get_items_data();
        $exchange->give($other, 2, $pierre->id);
        $exchange->items = [];
        $exchange->get_items_data();

        $exchange->cancelReturns($canceller, $other);

        $this->assertSame(5, $bois->get_n($canceller, includeInstances: false), 'the canceller is at the counter: bag');
        $this->assertSame(2, $pierre->get_n($other, bank: true, includeInstances: false), 'the other party is not: bank');
    }

    public function testCancelIsRefusedWhenTheCancellersBagIsFull(): void
    {
        $canceller = $this->createRealPlayer('GmCancelFull');
        $other = $this->createRealPlayer('GmOtherFull');
        $bois = $this->itemOrSkip('bois');
        $pierre = $this->itemOrSkip('pierre');
        $bois->add_item($canceller, 5);

        $exchange = $this->openExchange($canceller->id, $other->id);
        $exchange->get_items_data();
        $exchange->give($canceller, 5, $bois->id);
        $exchange->items = [];
        $exchange->get_items_data();
        $pierre->add_item($canceller, 1);

        $race = (string) $this->link->fetchOne('SELECT race FROM players WHERE id = ?', [$canceller->id]);
        $before = (int) $this->link->fetchOne('SELECT capacity FROM races WHERE name = ?', [$race]);
        $this->link->executeStatement('UPDATE races SET capacity = 1 WHERE name = ?', [$race]);
        $this->refreshRaceCatalog();
        try {
            $exchange->cancelReturns($canceller, $other);
            $this->fail('a full bag must refuse the cancel');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Votre sac est plein.', $e->getMessage());
        } finally {
            $this->link->executeStatement('UPDATE races SET capacity = ? WHERE name = ?', [$before, $race]);
            $this->refreshRaceCatalog();
        }
        $this->assertSame(0, $bois->get_n($canceller, includeInstances: false), 'nothing came back');
    }
}
