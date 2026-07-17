<?php

namespace Tests\Player;

use App\Action\ActionFactory;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use App\Service\UniqueObjectService;
use Classes\Item;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Items Phase 3 golden masters (docs/design-items-instances.md §3.3) —
 * the map bridge round trip:
 *
 *   worn instance → placeInstance() → a 'unique' players row (race
 *   objet, observable, attackable) wrapping it → the real 'ramasser'
 *   action through the untouched executor → the instance is back in
 *   the taker's inventory WITH ITS WEAR — identity survives.
 *
 * Also pins: placing refuses a still-equipped instance, and ramasser
 * on a structure that wraps nothing (a palissade) fails cleanly.
 */
#[Group('items-golden-master')]
#[Group('entities-structure')]
class UniqueObjectBridgeGoldenMasterTest extends LegacyPlayerFixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->link->executeQuery('SELECT item_instance_id FROM unique_objects LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('unique_objects bridge unavailable (run migrations): ' . $e->getMessage());
        }
    }

    /** @return array{0:\Classes\Player,1:Item,2:int} player, gladius, worn unequipped instance id */
    private function playerWithWornGladius(): array
    {
        $item = Item::get_item_by_name('gladius');
        if ($item === false || $item === null) {
            $this->markTestSkipped("items catalog not seeded (no 'gladius' row).");
        }
        $item->get_data();

        $player = $this->createRealPlayer('GmLooter');
        $item->add_item($player, 1);
        $player->get_caracs();
        $player->equip($item);

        $instanceId = (int) $this->link->fetchOne(
            'SELECT l.instance_id FROM players_items_instances l
             JOIN item_instances i ON i.id = l.instance_id WHERE l.player_id = ? AND i.item_id = ?',
            [$player->id, $item->id]
        );
        $this->link->executeStatement('UPDATE item_instances SET durability = 60 WHERE id = ?', [$instanceId]);
        $player->equip($item); // unequip — worn, stays an instance

        return [$player, $item, $instanceId];
    }

    public function testPlaceRefusesAnEquippedInstance(): void
    {
        $item = Item::get_item_by_name('gladius');
        if ($item === false || $item === null) {
            $this->markTestSkipped("items catalog not seeded (no 'gladius' row).");
        }
        $item->get_data();
        $player = $this->createRealPlayer('GmLooter');
        $item->add_item($player, 1);
        $player->get_caracs();
        $player->equip($item);
        $instanceId = (int) $this->link->fetchOne(
            'SELECT instance_id FROM players_items_instances WHERE player_id = ?', [$player->id]
        );

        $this->expectException(\InvalidArgumentException::class);
        (new UniqueObjectService())->placeInstance($instanceId, (object) ['x' => 0, 'y' => 5, 'z' => 0, 'plan' => 'gaia']);
    }

    public function testWornInstanceRoundTripsThroughTheMapWithItsIdentity(): void
    {
        [$player, , $instanceId] = $this->playerWithWornGladius();

        // View::get_coords_id echoes when it creates a missing tile —
        // swallow it so a fresh DB doesn't turn the test risky.
        ob_start();
        try {
            $uniqueId = (new UniqueObjectService())->placeInstance(
                $instanceId,
                (object) ['x' => 0, 'y' => 5, 'z' => 0, 'plan' => 'gaia']
            );
            $this->movePlayerTo($player->id, 0, 4);
        } finally {
            ob_end_clean();
        }
        $this->trackEntityId($uniqueId);

        $this->assertSame(
            'unique',
            $this->link->fetchOne('SELECT player_type FROM players WHERE id = ?', [$uniqueId]),
            'the placed item is a unique map entity'
        );
        $this->assertSame(
            $instanceId,
            (int) $this->link->fetchOne('SELECT item_instance_id FROM unique_objects WHERE player_id = ?', [$uniqueId]),
            'the entity wraps the instance'
        );
        $this->assertFalse(
            $this->link->fetchOne('SELECT 1 FROM players_items_instances WHERE instance_id = ?', [$instanceId]),
            'the owner link is released — the map IS the location'
        );

        // Ramasser, through the real action and executor.
        $player = PlayerFactory::legacy($player->id);
        $player->getCoords();
        $player->get_caracs();
        $wrapped = PlayerFactory::legacy($uniqueId);
        $wrapped->getCoords();
        $wrapped->get_caracs();

        $action = ActionFactory::getAction('ramasser');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no 'ramasser' row).");
        }
        $results = (new ActionExecutorService($action, $player, $wrapped))->executeAction();

        $this->assertFalse($results->isBlocked(), 'adjacent ramasser must pass its conditions');
        $this->assertTrue($results->isSuccess());

        $this->assertSame(
            $player->id,
            (int) $this->link->fetchOne('SELECT player_id FROM players_items_instances WHERE instance_id = ?', [$instanceId]),
            'the instance is back in the taker inventory'
        );
        $this->assertSame(
            60,
            (int) $this->link->fetchOne('SELECT durability FROM item_instances WHERE id = ?', [$instanceId]),
            'identity — the wear — survived the round trip'
        );
        $this->assertFalse(
            $this->link->fetchOne('SELECT 1 FROM players WHERE id = ?', [$uniqueId]),
            'the map entity is gone'
        );
    }

    public function testRamasserOnAStructureWrappingNothingFailsCleanly(): void
    {
        $player = $this->createRealPlayer('GmLooter');
        $buildingId = (new \App\Service\BuildingService())->place(
            'palissade',
            (object) ['x' => 0, 'y' => 1, 'z' => 0, 'plan' => 'gaia']
        );
        $this->trackEntityId($buildingId);

        $player->getCoords();
        $player->get_caracs();
        $building = PlayerFactory::legacy($buildingId);
        $building->getCoords();
        $building->get_caracs();

        $action = ActionFactory::getAction('ramasser');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no 'ramasser' row).");
        }
        $results = (new ActionExecutorService($action, $player, $building))->executeAction();

        $this->assertFalse($results->isBlocked(), 'conditions pass — a palissade IS a structure');
        $this->assertNotFalse(
            $this->link->fetchOne('SELECT 1 FROM players WHERE id = ?', [$buildingId]),
            'the palissade must still be there — nothing was taken'
        );
    }
}
