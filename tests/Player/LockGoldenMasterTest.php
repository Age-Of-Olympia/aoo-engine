<?php

namespace Tests\Player;

use App\Service\BuildingService;
use App\Service\LockService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Ce qui se ferme, et qui a le droit de le fermer.
 *
 * Deux questions, une par niveau : le TYPE dit ce qui a une porte, l'ENTITÉ dit
 * à qui elle appartient. Un coffre et un mur sont tous deux des « obstacles »
 * au catalogue — c'est précisément pourquoi la fermeture ne peut pas se déduire
 * de `structure_nature` et demande son propre drapeau.
 */
#[Group('entities-structure')]
class LockGoldenMasterTest extends LegacyPlayerFixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBuildingsOrSkip();

        try {
            $this->link->executeQuery('SELECT lockable FROM races LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('races.lockable absente (lancer les migrations) : ' . $e->getMessage());
        }
    }

    private function lock(): LockService
    {
        return new LockService($this->link);
    }

    /** Un mur n'a pas de porte : rien à fermer. */
    public function testAWallCannotBeShut(): void
    {
        $id = $this->placeStructure('palissade', 0, 3);

        $this->assertFalse($this->lock()->isLockable($id), 'une palissade ne se ferme pas');

        $this->expectException(\InvalidArgumentException::class);
        (new BuildingService())->setOpen($id, false);
    }

    /** Un coffre en a une, bien qu'il soit un « obstacle » comme le mur. */
    public function testAChestCanBeShutEvenThoughItIsAnObstacle(): void
    {
        $id = $this->placeStructure('coffre_bois', 0, 4);

        $this->assertSame(
            'obstacle',
            $this->link->fetchOne(
                'SELECT r.structure_nature FROM races r JOIN players p ON p.race = r.name WHERE p.id = ?',
                [$id]
            ),
            'le catalogue le range avec les murs — d\'où le drapeau séparé'
        );
        $this->assertTrue($this->lock()->isLockable($id), 'et pourtant il se ferme');

        (new BuildingService())->setOpen($id, false);
        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT is_open FROM players WHERE id = ?', [$id])
        );
    }

    /** Fermé volontairement ne se dit que de ce qui peut l'être. */
    public function testOnlyWhatCanBeShutReportsAVoluntaryClosure(): void
    {
        $service = new BuildingService();
        $chest = $this->placeStructure('coffre_bois', 0, 5);
        $wall = $this->placeStructure('palissade', 0, 6);

        $service->setOpen($chest, false);
        // Le mur reçoit le drapeau par la bande : la règle doit l'ignorer.
        $this->link->executeStatement('UPDATE players SET is_open = 0 WHERE id = ?', [$wall]);

        $this->assertSame(
            'fermé volontairement',
            $service->closureReason($chest, $service->getDetails($chest), 100)
        );
        $this->assertNull(
            $service->closureReason($wall, $service->getDetails($wall), 100),
            'un mur au drapeau baissé n\'est pas « fermé » : il n\'a pas de porte'
        );
    }

    /** La clé est à qui possède : en propre, ou par sa faction. */
    public function testTheKeyBelongsToTheOwnerOrToTheFaction(): void
    {
        $owner = $this->createRealPlayer('GmProprio');
        $stranger = $this->createRealPlayer('GmPassant');
        $id = $this->placeStructure('coffre_bois', 0, 7);

        $this->assertFalse(
            $this->lock()->mayLock($id, (int) $owner->id),
            'sans maître ni faction, personne n\'est chez soi'
        );

        $this->link->executeStatement('UPDATE players SET owner_id = ? WHERE id = ?', [$owner->id, $id]);
        $this->assertTrue($this->lock()->mayLock($id, (int) $owner->id));
        $this->assertFalse($this->lock()->mayLock($id, (int) $stranger->id), 'ce n\'est pas son coffre');

        // Par faction, maintenant : le coffre est à la forge, le passant aussi.
        $this->link->executeStatement(
            "UPDATE players SET owner_id = NULL, faction = 'forge_sacree' WHERE id = ?",
            [$id]
        );
        $this->link->executeStatement(
            "UPDATE players SET faction = 'forge_sacree' WHERE id = ?",
            [$stranger->id]
        );
        $this->assertTrue(
            $this->lock()->mayLock($id, (int) $stranger->id),
            'la faction ouvre à qui en est'
        );

        /* Le propriétaire n'est plus de la faction — et il faut le DIRE : une
         * race porte une faction de départ, si bien qu'un personnage jeté là
         * sans y penser en est déjà membre. */
        $this->link->executeStatement("UPDATE players SET faction = '' WHERE id = ?", [$owner->id]);
        $this->assertFalse(
            $this->lock()->mayLock($id, (int) $owner->id),
            'et se ferme à qui n\'en est pas'
        );
    }

    /** Un mur ne se ferme pour personne, propriétaire compris. */
    public function testNobodyLocksWhatHasNoDoor(): void
    {
        $owner = $this->createRealPlayer('GmMaçon');
        $id = $this->placeStructure('palissade', 0, 8);
        $this->link->executeStatement('UPDATE players SET owner_id = ? WHERE id = ?', [$owner->id, $id]);

        $this->assertFalse($this->lock()->mayLock($id, (int) $owner->id));
    }
}
