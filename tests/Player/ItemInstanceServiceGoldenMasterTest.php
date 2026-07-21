<?php

namespace Tests\Player;

use App\Service\ItemInstanceService;
use Classes\Item;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Items Phase 1a golden masters (docs/design-items-instances.md §5c):
 * the instance lifecycle under lazy promotion —
 *
 *   - promote(): stack −1 + instance + link, atomically; refused on an
 *     empty stack (P3);
 *   - create(): craft birth with creator and custom name (the only
 *     naming moment);
 *   - demote(): the REVERSIBILITY proof — a pristine instance returns
 *     to its stack; any diverged state (wear, name) refuses;
 *   - countOwned(): the future dual-read contract of get_n — stack
 *     units + live instances, destroyed excluded (P2).
 */
#[Group('items-golden-master')]
class ItemInstanceServiceGoldenMasterTest extends LegacyPlayerFixtureTestCase
{
    private function boisOrSkip(): Item
    {
        // Le nettoyage des instances des joueurs jetables est porté par le
        // teardown du harnais (liens puis lignes orphelines, par id tracké).
        try {
            $this->link->executeQuery('SELECT 1 FROM item_instances LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('item_instances table unavailable (run migrations): ' . $e->getMessage());
        }

        return $this->itemOrSkip('bois');
    }

    public function testPromoteMovesOneUnitFromStackToInstance(): void
    {
        $player = $this->createRealPlayer('GmSmith');
        $bois = $this->boisOrSkip();
        $bois->add_item($player, 3);

        $service = new ItemInstanceService();
        $instanceId = $service->promote($player->id, $bois->id);

        $this->assertSame(2, (int) $this->link->fetchOne(
            'SELECT n FROM players_items WHERE player_id = ? AND item_id = ?',
            [$player->id, $bois->id]
        ), 'the stack loses exactly one unit');

        $instance = $this->link->fetchAssociative('SELECT * FROM item_instances WHERE id = ?', [$instanceId]);
        $this->assertNotFalse($instance);
        $this->assertSame($bois->id, (int) $instance['item_id']);
        $this->assertSame((int) $instance['durability_max'], (int) $instance['durability'], 'born pristine');

        $this->assertSame($player->id, (int) $this->link->fetchOne(
            'SELECT player_id FROM players_items_instances WHERE instance_id = ?',
            [$instanceId]
        ), 'the link carries ownership');

        $this->assertSame(3, $service->countOwned($player->id, $bois->id), 'total owned is unchanged by promotion');
    }

    public function testPromoteRefusesAnEmptyStack(): void
    {
        $player = $this->createRealPlayer('GmSmith');
        $bois = $this->boisOrSkip();

        $this->expectException(\RuntimeException::class);
        (new ItemInstanceService())->promote($player->id, $bois->id);
    }

    public function testCreateBirthsANamedInstanceWithProvenance(): void
    {
        $player = $this->createRealPlayer('GmSmith');
        $bois = $this->boisOrSkip();

        $instanceId = (new ItemInstanceService())->create($player->id, $bois->id, $player->id, 'Dette de Thétis');

        $instance = $this->link->fetchAssociative('SELECT * FROM item_instances WHERE id = ?', [$instanceId]);
        $this->assertSame('Dette de Thétis', $instance['custom_name']);
        $this->assertSame($player->id, (int) $instance['creator_id']);
        $this->assertGreaterThan(0, (int) $instance['created_at']);
    }

    public function testDemoteReturnsAPristineInstanceToTheStackAndRefusesDivergedState(): void
    {
        $player = $this->createRealPlayer('GmSmith');
        $bois = $this->boisOrSkip();
        $bois->add_item($player, 1);

        $service = new ItemInstanceService();
        $instanceId = $service->promote($player->id, $bois->id);
        $this->assertFalse(
            $this->link->fetchOne('SELECT 1 FROM players_items WHERE player_id = ? AND item_id = ?', [$player->id, $bois->id]),
            'the emptied stack row is gone after promoting the last unit'
        );

        $this->assertTrue($service->demote($instanceId), 'a pristine instance must demote');
        $this->assertSame(1, (int) $this->link->fetchOne(
            'SELECT n FROM players_items WHERE player_id = ? AND item_id = ?',
            [$player->id, $bois->id]
        ), 'the unit is back in the stack');
        $this->assertFalse(
            $this->link->fetchOne('SELECT 1 FROM item_instances WHERE id = ?', [$instanceId]),
            'the pristine instance row is removed'
        );

        // Diverged state refuses: wear...
        $worn = $service->promote($player->id, $bois->id);
        $this->link->executeStatement('UPDATE item_instances SET durability = durability - 10 WHERE id = ?', [$worn]);
        $this->assertFalse($service->demote($worn), 'a worn instance must NOT demote');

        // ...and a name set at creation.
        $named = $service->create($player->id, $bois->id, $player->id, 'Labrys');
        $this->assertFalse($service->demote($named), 'a named instance must NOT demote');
    }

    public function testEquipWithoutChosenInstancePromotesFromTheStackBeforeReusingAWornInstance(): void
    {
        // Retour terrain saison 3 : « équiper » sur un lot de 5 arcs neufs
        // équipait la plus vieille instance — l'arc abîmé. Sans instance
        // choisie, la pile prime ; l'instance usée ne s'équipe qu'en
        // cliquant SA ligne (instanceId explicite).
        $player = $this->createRealPlayer('GmSmith');
        $bois = $this->boisOrSkip();
        $bois->add_item($player, 3);

        $service = new ItemInstanceService();
        $worn = $service->promote($player->id, $bois->id);
        $this->link->executeStatement('UPDATE item_instances SET durability = durability - 10 WHERE id = ?', [$worn]);

        $equipped = $service->equipCatalogItem($player->id, $bois->id, 'main1');

        $this->assertNotSame($worn, $equipped, 'the worn unequipped instance is NOT silently equipped');
        $this->assertSame(1, (int) $this->link->fetchOne(
            'SELECT n FROM players_items WHERE player_id = ? AND item_id = ?',
            [$player->id, $bois->id]
        ), 'the pristine unit came from the stack');
        $this->assertSame('', (string) $this->link->fetchOne(
            'SELECT equiped FROM players_items_instances WHERE instance_id = ?', [$worn]
        ), 'the worn instance stays unequipped');

        // Stack exhausted: the worn instance becomes the legitimate fallback.
        $this->link->executeStatement('DELETE FROM players_items WHERE player_id = ? AND item_id = ?', [$player->id, $bois->id]);
        $this->link->executeStatement('DELETE FROM players_items_instances WHERE instance_id = ?', [$equipped]);
        $this->link->executeStatement('DELETE FROM item_instances WHERE id = ?', [$equipped]);
        $service->equipCatalogItem($player->id, $bois->id, 'main1');
        $this->assertSame('main1', (string) $this->link->fetchOne(
            'SELECT equiped FROM players_items_instances WHERE instance_id = ?', [$worn]
        ), 'with an empty stack the oldest unequipped instance is reused');
    }

    public function testCountOwnedExcludesDestroyedInstances(): void
    {
        $player = $this->createRealPlayer('GmSmith');
        $bois = $this->boisOrSkip();
        $bois->add_item($player, 2);

        $service = new ItemInstanceService();
        $instanceId = $service->promote($player->id, $bois->id);
        $this->assertSame(2, $service->countOwned($player->id, $bois->id));

        $this->link->executeStatement('UPDATE item_instances SET destroyed = 1 WHERE id = ?', [$instanceId]);
        $this->assertSame(1, $service->countOwned($player->id, $bois->id), 'a destroyed instance no longer counts');
    }
}
