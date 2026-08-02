<?php

namespace Tests\Player;

use App\Service\ItemInstanceService;
use App\Service\Map\EntityLocationService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/** The entity says who holds an exemplar; the link table must agree with it. */
#[Group('items-golden-master')]
class HolderLivesOnTheEntityTest extends LegacyPlayerFixtureTestCase
{
    /** @return array{0:int,1:int} instance id, entity id */
    private function ownedGladius(string $name): array
    {
        $player = $this->createRealPlayer($name);
        $gladius = $this->itemOrSkip('gladius');

        $instanceId = (new ItemInstanceService())
            ->create((int) $player->id, (int) $gladius->id, (int) $player->id, '');
        $entityId = (int) $this->link->fetchOne(
            'SELECT entity_id FROM item_instances WHERE id = ?',
            [$instanceId]
        );
        $this->trackEntityId($entityId);

        return [$instanceId, $entityId];
    }

    /** @return array{holder_id: ?int, slot: string} */
    private function locationOf(int $entityId): array
    {
        $row = $this->link->fetchAssociative(
            'SELECT holder_id, slot FROM players WHERE id = ?',
            [$entityId]
        );

        return [
            'holder_id' => $row['holder_id'] === null ? null : (int) $row['holder_id'],
            'slot' => (string) $row['slot'],
        ];
    }

    public function testCreatingAnInstanceRecordsItsHolderOnTheEntity(): void
    {
        [$instanceId, $entityId] = $this->ownedGladius('GmPorteurA');

        $owner = (int) $this->link->fetchOne(
            'SELECT player_id FROM players_items_instances WHERE instance_id = ?',
            [$instanceId]
        );

        $this->assertSame(
            ['holder_id' => $owner, 'slot' => EntityLocationService::SLOT_CARRIED],
            $this->locationOf($entityId),
            'carried: held by its owner, in no particular slot'
        );
    }

    public function testTheBankIsASlotLikeAnyOther(): void
    {
        [$instanceId, $entityId] = $this->ownedGladius('GmPorteurB');
        $service = new ItemInstanceService();
        $owner = (int) $this->link->fetchOne(
            'SELECT player_id FROM players_items_instances WHERE instance_id = ?',
            [$instanceId]
        );

        $service->storeInBank($instanceId, $owner);
        $this->assertSame(
            ['holder_id' => $owner, 'slot' => ItemInstanceService::LOCATION_BANK],
            $this->locationOf($entityId),
            'shelved at the bank, still owned'
        );

        $service->withdrawFromBank($instanceId, $owner);
        $this->assertSame(
            ['holder_id' => $owner, 'slot' => EntityLocationService::SLOT_CARRIED],
            $this->locationOf($entityId),
            'back in hand'
        );
    }

    public function testDroppingClearsTheHolderAndPuttingItBackRestoresIt(): void
    {
        [$instanceId, $entityId] = $this->ownedGladius('GmPorteurC');
        $service = new ItemInstanceService();

        $coordsId = (int) \Classes\View::get_coords_id(
            (object) ['x' => 9, 'y' => 2, 'z' => 0, 'plan' => 'gaia']
        );
        $service->dropAt($instanceId, $coordsId);

        $onGround = $this->locationOf($entityId);
        $this->assertNull($onGround['holder_id'], 'on the ground, nobody holds it');
        $this->assertSame(EntityLocationService::SLOT_DROPPED, $onGround['slot']);

        $walker = $this->createRealPlayer('GmRamasseurC');
        $service->collectAt($coordsId, (int) $walker->id);

        $this->assertSame(
            ['holder_id' => (int) $walker->id, 'slot' => EntityLocationService::SLOT_CARRIED],
            $this->locationOf($entityId),
            'picked up: the walker holds it'
        );
    }

    /** Nothing anywhere may disagree — the invariant the collapse rests on. */
    public function testNoExemplarDisagreesWithItsLink(): void
    {
        $disagreeing = $this->link->fetchAllAssociative(
            "SELECT p.id, p.holder_id, p.slot, l.player_id, l.equiped, l.location
               FROM players p
               JOIN item_instances ii ON ii.entity_id = p.id
               JOIN players_items_instances l ON l.instance_id = ii.id
              WHERE p.holder_id <> l.player_id
                 OR p.slot <> CASE
                                  WHEN l.equiped <> '' THEN l.equiped
                                  WHEN l.location <> 'inventory' THEN l.location
                                  ELSE ''
                              END"
        );

        $this->assertSame([], $disagreeing, json_encode($disagreeing));
    }
}
