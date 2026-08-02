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
    private function boisOrSkip(): Item
    {
        try {
            $this->link->executeQuery('SELECT instance_id FROM players_items_exchanges LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('players_items_exchanges.instance_id unavailable: ' . $e->getMessage());
        }

        return $this->itemOrSkip('bois');
    }

    /** Un exemplaire usé, rangé en banque — l'état de départ. */
    private function bankedInstance(int $playerId, Item $item, int $durability = 35): int
    {
        $service = new ItemInstanceService();
        $instanceId = $service->promote($playerId, $item->id);
        /* Plus de maximum par exemplaire — il vient du type. La consigne
         * devient donc « ce qu'il lui reste », exprimé sur ce maximum. */
        $this->setRemainingLife($instanceId, $durability);
        $service->storeInBank($instanceId, $playerId);

        return $instanceId;
    }

    private function openExchange(int $playerId, int $targetId): Exchange
    {
        $exchange = new Exchange();
        $exchange->create($playerId, $targetId);
        $exchange->get_base_data();

        return $exchange;
    }

    public function testAnInstanceInPlayLeavesTheVaultWithoutTouchingAnyStack(): void
    {
        $offering = $this->createRealPlayer('GmOffer');
        $target = $this->createRealPlayer('GmTarget');
        $bois = $this->boisOrSkip();

        $bois->add_item($offering, 1);
        $instanceId = $this->bankedInstance($offering->id, $bois);
        $bois->add_item($offering, 4, bank: true);

        $service = new ItemInstanceService();
        $service->escrowForExchange($instanceId, $offering->id);

        $exchange = $this->openExchange($offering->id, $target->id);
        $exchange->add_item_to_exchange($bois->id, 1, $offering->id, $instanceId);

        $this->assertSame(4, (int) $this->link->fetchOne(
            'SELECT n FROM players_items_bank WHERE player_id = ? AND item_id = ?',
            [$offering->id, $bois->id]
        ), 'la pile de banque est intacte');

        $this->assertSame(
            ItemInstanceService::LOCATION_EXCHANGE,
            (string) $this->link->fetchOne(
                'SELECT location FROM players_items_instances WHERE instance_id = ?',
                [$instanceId]
            )
        );
        $this->assertSame([], $service->listForBank($offering->id), 'hors banque tant qu\'il est en jeu');
        $this->assertFalse($service->hasEquippableUnit($offering->id, $bois->id), 'et non équipable');
    }

    public function testSettlingTransfersIdentityAndClearsTheLines(): void
    {
        $offering = $this->createRealPlayer('GmOffer');
        $target = $this->createRealPlayer('GmTarget');
        $bois = $this->boisOrSkip();
        $bois->add_item($offering, 1);

        $service = new ItemInstanceService();
        $instanceId = $this->bankedInstance($offering->id, $bois);
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
            'SELECT player_id, location FROM players_items_instances WHERE instance_id = ?',
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
        $bois = $this->boisOrSkip();
        $bois->add_item($offering, 2);

        $service = new ItemInstanceService();
        $first = $this->bankedInstance($offering->id, $bois, 5);
        $second = $this->bankedInstance($offering->id, $bois, 12);
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

    public function testWithdrawingFromTheExchangeBringsTheInstanceBack(): void
    {
        $offering = $this->createRealPlayer('GmOffer');
        $target = $this->createRealPlayer('GmTarget');
        $bois = $this->boisOrSkip();
        $bois->add_item($offering, 1);

        $service = new ItemInstanceService();
        $instanceId = $this->bankedInstance($offering->id, $bois);
        $service->escrowForExchange($instanceId, $offering->id);
        $service->releaseFromExchange($instanceId, $offering->id);

        $bank = $service->listForBank($offering->id);
        $this->assertCount(1, $bank);
        $this->assertSame(35, (int) $bank[0]['durability'], 'avec son usure');
    }

    public function testTheDatabaseRefusesOneInstanceInTwoExchanges(): void
    {
        $offering = $this->createRealPlayer('GmOffer');
        $target = $this->createRealPlayer('GmTarget');
        $bois = $this->boisOrSkip();
        $bois->add_item($offering, 1);
        $instanceId = $this->bankedInstance($offering->id, $bois);

        $first = $this->openExchange($offering->id, $target->id);
        $second = $this->openExchange($offering->id, $target->id);

        $first->add_item_to_exchange($bois->id, 1, $offering->id, $instanceId);

        // L'index UNIQUE tient même si la garde applicative est
        // contournée : un exemplaire ne peut être engagé qu'une fois.
        $this->expectException(\Throwable::class);
        $second->add_item_to_exchange($bois->id, 1, $offering->id, $instanceId);
    }
}
