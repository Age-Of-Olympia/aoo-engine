<?php

namespace Tests\Various;

use App\Service\BuildingService;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Asking where something is never puts it somewhere.
 *
 * `coords_id = NULL` means three different things (overview §3): held inside
 * another entity, shelved off the world, or nowhere yet. Only the first has a
 * cell to report — its holder's — and none of the three is an invitation to
 * place the thing.
 */
#[Group('entities-baseline')]
class WhereAThingStandsTest extends LegacyPlayerFixtureTestCase
{
    /** A sword in a hand is on its bearer's tile. */
    public function testAHeldExemplarStandsWhereItsHolderStands(): void
    {
        [$x, $y] = $this->farTile();
        $bearer = $this->createRealPlayer('GmPorteur');
        $this->movePlayerTo((int) $bearer->id, $x, $y);

        $entityId = $this->installExemplar('baton_marche', $x, $y, (int) $bearer->id);

        // Held, not installed: no cell of its own any more.
        $this->link->executeStatement(
            "UPDATE players SET coords_id = NULL, holder_id = ?, slot = '' WHERE id = ?",
            [(int) $bearer->id, $entityId]
        );
        $this->link->executeStatement('DELETE FROM entity_cells WHERE player_id = ?', [$entityId]);

        $coords = (new Player($entityId))->getCoords();

        $this->assertNotNull($coords, 'what is carried is where its bearer is');
        $this->assertSame($x, (int) $coords->x);
        $this->assertSame($y, (int) $coords->y);
    }

    /** And asking did not put it on the board. */
    public function testAskingWhereAHeldExemplarIsDoesNotPlaceIt(): void
    {
        [$x, $y] = $this->farTile();
        $bearer = $this->createRealPlayer('GmPorteur2');
        $this->movePlayerTo((int) $bearer->id, $x, $y);

        $entityId = $this->installExemplar('baton_marche', $x, $y, (int) $bearer->id);
        $this->link->executeStatement(
            "UPDATE players SET coords_id = NULL, holder_id = ?, slot = '' WHERE id = ?",
            [(int) $bearer->id, $entityId]
        );
        $this->link->executeStatement('DELETE FROM entity_cells WHERE player_id = ?', [$entityId]);

        (new Player($entityId))->getCoords();

        $this->assertNull(
            $this->link->fetchOne('SELECT coords_id FROM players WHERE id = ?', [$entityId]),
            'a read leaves it held'
        );
        $this->assertSame(
            0,
            (int) $this->link->fetchOne(
                'SELECT COUNT(*) FROM entity_cells WHERE player_id = ?',
                [$entityId]
            ),
            'and holding no tile'
        );
    }

    /**
     * A destroyed building keeps its row on purpose, off the world. Asking
     * where it is answers "nowhere" instead of resurrecting it.
     */
    public function testAShelvedBuildingStandsNowhereAndStaysThere(): void
    {
        [$x, $y] = $this->farTile();
        $id = $this->placeStructure('mur_pierre', $x, $y);

        (new BuildingService())->vanish($id);

        $shelved = new Player($id);

        $this->assertNull($shelved->getCoords(), 'shelved is nowhere, not (0,0)');

        $this->assertNull(
            $this->link->fetchOne('SELECT coords_id FROM players WHERE id = ?', [$id]),
            'and the read did not put it back on the board'
        );
        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT COUNT(*) FROM entity_cells WHERE player_id = ?', [$id]),
            'nor gave it a tile'
        );
    }

    /** A placed entity still answers its own cell. */
    public function testAPlacedEntityAnswersItsOwnCell(): void
    {
        [$x, $y] = $this->farTile();
        $player = $this->createRealPlayer('GmPose');
        $this->movePlayerTo((int) $player->id, $x, $y);

        $coords = (new Player((int) $player->id))->getCoords();

        $this->assertNotNull($coords);
        $this->assertSame($x, (int) $coords->x);
        $this->assertSame($y, (int) $coords->y);
        $this->assertSame('gaia', $coords->plan);
    }
}
