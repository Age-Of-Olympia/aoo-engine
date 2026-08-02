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
#[Group('items-baseline')]
class ItemInstanceServiceBaselineTest extends LegacyPlayerFixtureTestCase
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
        $this->assertSame(
            $this->maxLifeOf($instanceId),
            $this->remainingLifeOf($instanceId),
            'born pristine — and pristine means no deficit row at all'
        );
        $this->assertFalse(
            $this->link->fetchOne(
                "SELECT 1 FROM players_bonus WHERE player_id = ? AND name = 'pv'",
                [(int) $instance['entity_id']]
            ),
            'un exemplaire neuf ne porte aucune ligne, comme un personnage indemne'
        );

        $this->assertSame($player->id, (int) $this->link->fetchOne(
            'SELECT e.holder_id FROM players e JOIN item_instances i ON i.entity_id = e.id WHERE i.id = ?',
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
        $this->setRemainingLife($worn, $this->maxLifeOf($worn) - 10);
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
        $this->setRemainingLife($worn, $this->maxLifeOf($worn) - 10);

        $equipped = $service->equipCatalogItem($player->id, $bois->id, 'main1');

        $this->assertNotSame($worn, $equipped, 'the worn unequipped instance is NOT silently equipped');
        $this->assertSame(1, (int) $this->link->fetchOne(
            'SELECT n FROM players_items WHERE player_id = ? AND item_id = ?',
            [$player->id, $bois->id]
        ), 'the pristine unit came from the stack');
        $this->assertSame('', (string) $this->link->fetchOne(
            'SELECT e.slot FROM players e JOIN item_instances i ON i.entity_id = e.id WHERE i.id = ?', [$worn]
        ), 'the worn instance stays unequipped');

        // Stack exhausted: the worn instance becomes the legitimate fallback.
        // Deleting the exemplar by hand means taking its entity too — teardown
        // finds entities through the ownership link, which goes first here.
        $equippedEntity = $this->link->fetchOne('SELECT entity_id FROM item_instances WHERE id = ?', [$equipped]);
        $this->link->executeStatement('DELETE FROM players_items WHERE player_id = ? AND item_id = ?', [$player->id, $bois->id]);
        $this->link->executeStatement(
            'UPDATE players e JOIN item_instances i ON i.entity_id = e.id SET e.holder_id = NULL WHERE i.id = ?',
            [$equipped]
        );
        $this->link->executeStatement('DELETE FROM item_instances WHERE id = ?', [$equipped]);
        if ($equippedEntity !== false && $equippedEntity !== null) {
            $this->link->executeStatement('DELETE FROM players WHERE id = ?', [(int) $equippedEntity]);
        }
        $service->equipCatalogItem($player->id, $bois->id, 'main1');
        $this->assertSame('main1', (string) $this->link->fetchOne(
            'SELECT e.slot FROM players e JOIN item_instances i ON i.entity_id = e.id WHERE i.id = ?', [$worn]
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
