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

        /* No lootChance pin: a container is EMPTIED, not looted — its
         * stored goods drop whole, whatever the catalog chance says. */
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
    }

    public function testASmashedChestEmptiesItsStacksWhole(): void
    {
        [$chestId, $swordId, $coordsId] = $this->chestHoldingASword(6, 7);
        $this->trackEntityId($swordId);

        $bois = $this->link->fetchAssociative("SELECT id FROM items WHERE name = 'bois'");
        if ($bois === false) {
            $this->markTestSkipped('items catalog not seeded.');
        }
        $this->link->executeStatement(
            "INSERT INTO players_items (player_id, item_id, n, equiped, slot) VALUES (?, ?, 7, '', '')
             ON DUPLICATE KEY UPDATE n = 7",
            [$chestId, (int) $bois['id']]
        );

        $onTile = fn (): int => (int) $this->link->fetchOne(
            'SELECT COALESCE(SUM(n), 0) FROM map_items WHERE coords_id = ? AND item_id = ?',
            [$coordsId, (int) $bois['id']]
        );
        $before = $onTile();

        $this->assertTrue((new BuildingService())->vanish($chestId));

        $this->assertSame(
            $before + 7,
            $onTile(),
            'every unit lands on the tile: cargo, not battle wreckage'
        );
    }

    public function testAStackFilledContainerIsNotPocketedEither(): void
    {
        $owner = $this->createRealPlayer('GmPorteurPile');
        $chestId = $this->installExemplar('coffre_bois', 6, 9, (int) $owner->id);
        $bois = $this->link->fetchAssociative("SELECT id FROM items WHERE name = 'bois'");
        if ($bois === false) {
            $this->markTestSkipped('items catalog not seeded.');
        }
        $this->link->executeStatement(
            "INSERT INTO players_items (player_id, item_id, n, equiped, slot) VALUES (?, ?, 2, '', '')
             ON DUPLICATE KEY UPDATE n = 2",
            [$chestId, (int) $bois['id']]
        );

        $this->assertTrue(
            (new EntityLocationService($this->link))->holdsAnything($chestId),
            'stacks count as holdings — a chest of wood is not pocketed whole'
        );
    }

    public function testBrokenDoesNotStandBackUp(): void
    {
        $owner = $this->createRealPlayer('GmBrise');
        $chestId = $this->installExemplar('coffre_bois', 6, 11, (int) $owner->id);
        $instanceId = (int) $this->link->fetchOne(
            'SELECT id FROM item_instances WHERE entity_id = ?',
            [$chestId]
        );

        // Smashed to zero: the wear bonus carries the full deficit.
        $this->link->executeStatement(
            "INSERT INTO players_bonus (player_id, name, n)
             SELECT ?, 'pv', -it.durability_max FROM item_instances i JOIN items it ON it.id = i.item_id WHERE i.id = ?
             ON DUPLICATE KEY UPDATE n = VALUES(n)",
            [$chestId, $instanceId]
        );
        (new \App\Service\Map\EntityLocationService($this->link))->putInside($chestId, (int) $owner->id);

        $this->expectExceptionMessage('Brisé, cela ne se pose plus');
        (new \App\Service\PlacedExemplarService())->placeInstance(
            $instanceId,
            (object) ['x' => 7, 'y' => 11, 'z' => 0, 'plan' => 'gaia']
        );
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
