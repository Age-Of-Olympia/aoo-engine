<?php

namespace Tests\Various;

use App\Service\Map\EntityCellService;
use App\Service\Map\EntityLocationService;
use Classes\View;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Where an entity is: on a cell, inside another, or nowhere.
 *
 * These cases pin the invariant rather than the columns — the three doors each
 * close the others, so no entity ever stands in two places, and none keeps a
 * square of the board after being picked up. The chain cases matter most: what
 * a held thing answers when asked where it is decides whether an item in a bag
 * can be reached by anything that reasons about the map.
 *
 * Fixtures hold fixtures here. A character carrying a character is nonsense in
 * play, but the relation knows nothing of kinds — that is the point of it.
 */
class EntityLocationServiceTest extends LegacyPlayerFixtureTestCase
{
    private const PLAN = 'plan_test_contenance';

    protected function tearDown(): void
    {
        $link = $this->link;

        if ($link !== null) {
            /* La clé étrangère est RESTRICT : un porteur encore chargé refuse
             * d'être supprimé, et c'est voulu. Le harnais relâche donc avant
             * de démonter. */
            $link->executeStatement(
                "UPDATE players SET holder_id = NULL, slot = '' WHERE holder_id IS NOT NULL"
            );
            $link->executeStatement(
                'DELETE ec FROM entity_cells ec JOIN coords c ON c.id = ec.coords_id WHERE c.plan = ?',
                [self::PLAN]
            );
        }

        parent::tearDown();

        $link?->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
    }

    private function coordsId(int $x, int $y): int
    {
        return (int) View::get_coords_id(
            (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => self::PLAN]
        );
    }

    private function service(): EntityLocationService
    {
        return new EntityLocationService($this->link);
    }

    /** @return array{0: int, 1: int} raw coords_id and holder_id as stored */
    private function rawLocation(int $entityId): array
    {
        $row = $this->link->fetchAssociative(
            'SELECT coords_id, holder_id FROM players WHERE id = ?',
            [$entityId]
        );

        return [(int) $row['coords_id'], (int) $row['holder_id']];
    }

    /** Standing somewhere means standing nowhere else, and holding your cells. */
    public function testPlacingOnACellClearsAnyHolderAndLaysTheCells(): void
    {
        $carrier = $this->createRealPlayer('ContenanceP');
        $carried = $this->createRealPlayer('ContenanceE');
        $service = $this->service();

        $service->putInside((int) $carried->id, (int) $carrier->id, 'main1');
        $cell = $this->coordsId(1, 1);
        $service->placeOnCell((int) $carried->id, $cell);

        [$coordsId, $holderId] = $this->rawLocation((int) $carried->id);
        $this->assertSame($cell, $coordsId);
        $this->assertSame(0, $holderId, 'reposé sur une case, il n\'est plus tenu');
        $this->assertSame($cell, $service->cellOf((int) $carried->id));
        $this->assertCount(
            1,
            (new EntityCellService($this->link))->cellsOf((int) $carried->id),
            'la case posée est reprise dans l\'emprise'
        );
    }

    /** Picked up is off the board: no cell of its own, and none left behind. */
    public function testBeingHeldReleasesTheCellAndItsFootprint(): void
    {
        $carrier = $this->createRealPlayer('ContenancePorteur');
        $carried = $this->createRealPlayer('ContenanceTenu');
        $service = $this->service();

        $service->placeOnCell((int) $carried->id, $this->coordsId(2, 2));
        $service->putInside((int) $carried->id, (int) $carrier->id);

        $row = $this->link->fetchAssociative(
            'SELECT coords_id, holder_id, slot FROM players WHERE id = ?',
            [$carried->id]
        );

        $this->assertNull($row['coords_id'], 'tenu, il n\'est sur aucune case');
        $this->assertSame((int) $carrier->id, (int) $row['holder_id']);
        $this->assertSame(EntityLocationService::SLOT_CARRIED, $row['slot']);
        $this->assertSame(
            [],
            (new EntityCellService($this->link))->cellsOf((int) $carried->id),
            'un objet ramassé ne garde pas une case du damier'
        );
    }

    /** A sword in a bag on a character is on the character's cell. */
    public function testTheCellIsFoundByClimbingTheHolders(): void
    {
        $bearer = $this->createRealPlayer('ContenanceChaineA');
        $bag    = $this->createRealPlayer('ContenanceChaineB');
        $sword  = $this->createRealPlayer('ContenanceChaineC');
        $service = $this->service();

        $cell = $this->coordsId(3, 3);
        $service->placeOnCell((int) $bearer->id, $cell);
        $service->putInside((int) $bag->id, (int) $bearer->id);
        $service->putInside((int) $sword->id, (int) $bag->id);

        $this->assertSame($cell, $service->cellOf((int) $sword->id), 'deux crans plus haut, la même case');
        $this->assertSame($cell, $service->cellOf((int) $bag->id));
        $this->assertSame((int) $bag->id, $service->holderOf((int) $sword->id));
    }

    /** Held by something that is nowhere is itself nowhere. */
    public function testAChainThatEndsNowhereAnswersNowhere(): void
    {
        $shelved = $this->createRealPlayer('ContenanceRemise');
        $held    = $this->createRealPlayer('ContenanceDedans');
        $service = $this->service();

        $service->putInside((int) $held->id, (int) $shelved->id);
        $service->shelve((int) $shelved->id);

        $this->assertNull($service->cellOf((int) $shelved->id));
        $this->assertNull($service->cellOf((int) $held->id));
    }

    /** Shelved: off the world, still a row — the events naming it stay true. */
    public function testShelvingLeavesTheRowAndTakesTheCells(): void
    {
        $entity = $this->createRealPlayer('ContenanceLimbes');
        $service = $this->service();

        $service->placeOnCell((int) $entity->id, $this->coordsId(4, 4));
        $service->shelve((int) $entity->id);

        $row = $this->link->fetchAssociative(
            'SELECT coords_id, holder_id FROM players WHERE id = ?',
            [$entity->id]
        );

        $this->assertNotFalse($row, 'remisée, la ligne existe toujours');
        $this->assertNull($row['coords_id']);
        $this->assertNull($row['holder_id']);
        $this->assertSame([], (new EntityCellService($this->link))->cellsOf((int) $entity->id));
    }

    /** An inventory is a query: what points at me, by slot or in full. */
    public function testChildrenAreTheInventory(): void
    {
        $chest  = $this->createRealPlayer('ContenanceCoffre');
        $inBag  = $this->createRealPlayer('ContenanceSac');
        $worn   = $this->createRealPlayer('ContenancePorte');
        $service = $this->service();

        $this->assertFalse($service->holdsAnything((int) $chest->id), 'vide au départ');

        $service->putInside((int) $inBag->id, (int) $chest->id);
        $service->putInside((int) $worn->id, (int) $chest->id, 'main1');

        $this->assertTrue($service->holdsAnything((int) $chest->id));
        $this->assertCount(2, $service->childrenOf((int) $chest->id));

        $equipped = $service->childrenOf((int) $chest->id, 'main1');
        $this->assertCount(1, $equipped);
        $this->assertSame((int) $worn->id, (int) $equipped[0]['id']);
    }

    /** A bag cannot go inside itself, nor inside what it already holds. */
    public function testAHolderChainCannotCloseOnItself(): void
    {
        $outer = $this->createRealPlayer('ContenanceBoucleA');
        $inner = $this->createRealPlayer('ContenanceBoucleB');
        $service = $this->service();

        $service->putInside((int) $inner->id, (int) $outer->id);

        $this->expectException(\InvalidArgumentException::class);
        $service->putInside((int) $outer->id, (int) $inner->id);
    }

    public function testAnEntityCannotHoldItself(): void
    {
        $lonely = $this->createRealPlayer('ContenanceSoi');

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->putInside((int) $lonely->id, (int) $lonely->id);
    }

    /**
     * A cycle written behind the service's back answers "nowhere" instead of
     * spinning — the depth guard earning its keep.
     */
    public function testACycleForcedIntoTheTableDoesNotHang(): void
    {
        $a = $this->createRealPlayer('ContenanceCycleA');
        $b = $this->createRealPlayer('ContenanceCycleB');

        $this->link->executeStatement(
            'UPDATE players SET coords_id = NULL, holder_id = ? WHERE id = ?',
            [(int) $b->id, (int) $a->id]
        );
        $this->link->executeStatement(
            'UPDATE players SET coords_id = NULL, holder_id = ? WHERE id = ?',
            [(int) $a->id, (int) $b->id]
        );

        $this->assertNull($this->service()->cellOf((int) $a->id));
    }

    /**
     * A container full of things refuses to be deleted.
     *
     * The schema says what the game says: a chest spills before it dies. Were
     * it ON DELETE SET NULL, smashing one would quietly scatter its contents
     * into nowhere instead of onto the floor.
     */
    public function testDeletingAHolderThatStillHoldsIsRefused(): void
    {
        $chest   = $this->createRealPlayer('ContenanceRestrictA');
        $content = $this->createRealPlayer('ContenanceRestrictB');

        $this->service()->putInside((int) $content->id, (int) $chest->id);

        $this->expectException(\Doctrine\DBAL\Exception::class);
        $this->link->executeStatement('DELETE FROM players WHERE id = ?', [(int) $chest->id]);
    }
}
