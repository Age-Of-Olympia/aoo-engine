<?php

namespace Tests\Player;

use App\Service\BuildingService;
use App\Service\ItemInstanceService;
use App\Service\Map\EntityLocationService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/** A container dying spills what it holds, through the code that kills anything. */
#[Group('entities-structure')]
class ChestSpillsWhenSmashedTest extends LegacyPlayerFixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBuildingsOrSkip();
    }

    /** @return array{0:int,1:int,2:int} chest entity, held exemplar entity, coords */
    private function chestHoldingASword(int $x, int $y): array
    {
        $owner = $this->createRealPlayer('GmSerrurier' . $x . $y);
        $chestId = $this->installExemplar('coffre_bois', $x, $y, (int) $owner->id);

        $gladius = $this->itemOrSkip('gladius');
        $instanceId = (new ItemInstanceService())
            ->create((int) $owner->id, (int) $gladius->id, (int) $owner->id, '');
        $swordId = (int) $this->link->fetchOne(
            'SELECT entity_id FROM item_instances WHERE id = ?',
            [$instanceId]
        );

        $this->link->executeStatement(
            'DELETE FROM players_items_instances WHERE instance_id = ?',
            [$instanceId]
        );
        (new EntityLocationService($this->link))->putInside($swordId, $chestId);

        $coordsId = (int) $this->link->fetchOne(
            'SELECT coords_id FROM players WHERE id = ?',
            [$chestId]
        );

        return [$chestId, $swordId, $coordsId];
    }

    public function testASmashedChestDropsItsContentsOnItsTile(): void
    {
        [$chestId, $swordId, $coordsId] = $this->chestHoldingASword(6, 3);
        $this->trackEntityId($swordId);

        $this->assertSame(
            $chestId,
            (int) $this->link->fetchOne('SELECT holder_id FROM players WHERE id = ?', [$swordId]),
            'the sword starts inside the chest'
        );

        // Contents take the same loot roll as a stack unit, so the chance is
        // pinned to make the case deterministic.
        $itemId = (int) $this->link->fetchOne(
            'SELECT item_id FROM item_instances WHERE entity_id = ?',
            [$swordId]
        );
        $before = $this->link->fetchOne('SELECT lootChance FROM items WHERE id = ?', [$itemId]);
        $this->link->executeStatement('UPDATE items SET lootChance = 100 WHERE id = ?', [$itemId]);

        try {
            $this->assertTrue((new BuildingService())->vanish($chestId), 'a chest dies like any structure');

            $sword = $this->link->fetchAssociative(
                'SELECT holder_id, coords_id, slot FROM players WHERE id = ?',
                [$swordId]
            );

            $this->assertNull($sword['holder_id'], 'it is held by nobody now');
            $this->assertSame($coordsId, (int) $sword['coords_id'], 'it lies on the chest tile');
            $this->assertSame(
                EntityLocationService::SLOT_DROPPED,
                $sword['slot'],
                'dropped, so it can be picked up — not installed'
            );
        } finally {
            $this->link->executeStatement(
                'UPDATE items SET lootChance = ? WHERE id = ?',
                [$before, $itemId]
            );
        }
    }

    public function testAFullContainerIsNotPocketed(): void
    {
        [$chestId, $swordId, $coordsId] = $this->chestHoldingASword(6, 5);
        $this->trackEntityId($swordId);

        $walker = $this->createRealPlayer('GmRamasseur');
        (new EntityLocationService($this->link))->dropOnCell($chestId, $coordsId);

        $taken = (new ItemInstanceService())->collectAt($coordsId, (int) $walker->id);

        $this->assertSame([], $taken, 'nothing is collected');
        $this->assertNull(
            $this->link->fetchOne('SELECT holder_id FROM players WHERE id = ?', [$chestId]),
            'the chest stays on the ground'
        );
        $this->assertSame(
            $chestId,
            (int) $this->link->fetchOne('SELECT holder_id FROM players WHERE id = ?', [$swordId]),
            'and keeps its contents'
        );
    }
}
