<?php

namespace Tests\Player;

use App\Service\BuildingService;
use App\Service\LockService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/** What shuts, and who holds the key: the type answers the first, the entity
 *  the second. */
#[Group('entities-structure')]
class LockBaselineTest extends LegacyPlayerFixtureTestCase
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

    public function testAWallCannotBeShut(): void
    {
        $id = $this->placeStructure('palissade', 0, 3);

        $this->assertFalse($this->lock()->isLockable($id), 'une palissade ne se ferme pas');

        $this->expectException(\InvalidArgumentException::class);
        (new BuildingService())->setOpen($id, false);
    }

    /** Shutting crosses catalogues: the wall answers by race, the chest by
     *  its item, and the caller cannot tell which spoke. */
    public function testAChestShutsThoughItsTypeIsAnItem(): void
    {
        $id = $this->installExemplar('coffre_bois', 0, 4);

        $this->assertSame(
            'item',
            $this->link->fetchOne('SELECT player_type FROM players WHERE id = ?', [$id]),
            'un coffre est un exemplaire, plus un bâtiment'
        );
        $this->assertNull(
            $this->link->fetchOne(
                'SELECT name FROM races WHERE CONVERT(name USING utf8mb4) = (
                     SELECT CONVERT(race USING utf8mb4) FROM players WHERE id = ?)',
                [$id]
            ) ?: null,
            'et aucune race ne le type plus'
        );
        $this->assertTrue($this->lock()->isLockable($id), 'et pourtant il se ferme');

        (new BuildingService())->setOpen($id, false);
        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT is_open FROM players WHERE id = ?', [$id])
        );
    }

    public function testOnlyWhatCanBeShutReportsAVoluntaryClosure(): void
    {
        $service = new BuildingService();
        $chest = $this->installExemplar('coffre_bois', 0, 5);
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

    public function testTheKeyBelongsToTheOwnerOrToTheFaction(): void
    {
        $owner = $this->createRealPlayer('GmProprio');
        $stranger = $this->createRealPlayer('GmPassant');
        $id = $this->installExemplar('coffre_bois', 0, 7);

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

    /**
     * Une PORTE ouverte se franchit ; fermée, elle barre comme le mur qu'elle
     * perce. C'est tout ce qu'une porte ajoute à un mur.
     *
     * Aucun type ne se déclare porte aujourd'hui : le cas en fabrique une, ce
     * qui est aussi la démonstration qu'il suffit de le dire au type.
     */
    public function testAnOpenDoorLetsYouThroughAndAShutOneDoesNot(): void
    {
        $mover = $this->createRealPlayer('GmVisiteur');
        $id = $this->placeStructure('palissade', 3, 3);
        $coordsId = (int) \Classes\View::get_coords_id(
            (object) ['x' => 3, 'y' => 3, 'z' => 0, 'plan' => 'gaia']
        );

        // Une palissade qui devient porte : elle se ferme, et sa fermeture
        // décide du passage.
        $this->link->executeStatement(
            "UPDATE races SET lockable = 1, opens_the_way = 1 WHERE name = 'palissade'"
        );
        \App\Service\RaceService::clearCache();
        \App\Factory\EntityManagerFactory::getEntityManager()->clear();

        try {
            $occupancy = new \App\Service\Map\TileOccupancyService($this->link);

            (new BuildingService())->setOpen($id, true);
            $this->assertNull(
                $occupancy->stepRefusal($coordsId, (int) $mover->id, true),
                'porte ouverte : on passe'
            );

            (new BuildingService())->setOpen($id, false);
            $this->assertNotNull(
                $occupancy->stepRefusal($coordsId, (int) $mover->id, true),
                'porte fermée : elle barre'
            );
        } finally {
            $this->link->executeStatement(
                "UPDATE races SET lockable = 0, opens_the_way = 0 WHERE name = 'palissade'"
            );
            \App\Service\RaceService::clearCache();
            \App\Factory\EntityManagerFactory::getEntityManager()->clear();
        \App\Factory\EntityManagerFactory::getEntityManager()->clear();
        }
    }

    /**
     * La même ouverture décide du pas ET du tir.
     *
     * Une porte ouverte est un trou dans le mur : la flèche y passe comme le
     * pied. Un seuil qu'on franchirait à pied mais pas du regard n'aurait
     * aucun sens.
     */
    public function testAnOpenDoorLetsArrowsThroughToo(): void
    {
        $this->placeStructure('palissade', 3, 9);
        $from = (object) ['x' => 0, 'y' => 9, 'z' => 0, 'plan' => 'gaia'];
        $to   = (object) ['x' => 6, 'y' => 9, 'z' => 0, 'plan' => 'gaia'];
        $id = (int) $this->link->fetchOne(
            "SELECT p.id FROM players p JOIN coords c ON c.id = p.coords_id
              WHERE c.x = 3 AND c.y = 9 AND c.plan = 'gaia' AND p.race = 'palissade'"
        );

        $service = new BuildingService();

        $this->assertNotNull(
            $service->lineOfFireReport($from, $to)['blocker'],
            'un mur ordinaire arrête la flèche'
        );

        $this->link->executeStatement(
            "UPDATE races SET lockable = 1, opens_the_way = 1 WHERE name = 'palissade'"
        );
        \App\Service\RaceService::clearCache();
        \App\Factory\EntityManagerFactory::getEntityManager()->clear();

        try {
            $service->setOpen($id, true);
            $this->assertNull(
                (new BuildingService())->lineOfFireReport($from, $to)['blocker'],
                'porte ouverte : la flèche passe'
            );

            $service->setOpen($id, false);
            $this->assertNotNull(
                (new BuildingService())->lineOfFireReport($from, $to)['blocker'],
                'porte fermée : elle arrête de nouveau'
            );
        } finally {
            $this->link->executeStatement(
                "UPDATE races SET lockable = 0, opens_the_way = 0 WHERE name = 'palissade'"
            );
            \App\Service\RaceService::clearCache();
            \App\Factory\EntityManagerFactory::getEntityManager()->clear();
        \App\Factory\EntityManagerFactory::getEntityManager()->clear();
        }
    }

    /**
     * Un ÉDIFICE ne se traverse pas, ouvert ou fermé.
     *
     * Sa fermeture décide de ses SERVICES — il tait son dialogue — jamais du
     * passage : on n'a jamais marché à travers une taverne.
     */
    public function testAnOpenBuildingIsStillNotWalkable(): void
    {
        $race = (new \App\Service\RaceService())->getRaceByName('taverne');
        if ($race === null || !$race->isEdifice()) {
            $this->markTestSkipped("type 'taverne' non seedé.");
        }

        $mover = $this->createRealPlayer('GmClient');
        $id = $this->placeStructure('taverne', 5, 5);
        $coordsId = (int) \Classes\View::get_coords_id(
            (object) ['x' => 5, 'y' => 5, 'z' => 0, 'plan' => 'gaia']
        );

        (new BuildingService())->setOpen($id, true);

        $this->assertNull(
            (new BuildingService())->closureReason($id, (new BuildingService())->getDetails($id), 100),
            'ouverte, elle rend ses services'
        );
        $this->assertNotNull(
            (new \App\Service\Map\TileOccupancyService($this->link))
                ->stepRefusal($coordsId, (int) $mover->id, true),
            'et on ne la traverse pas pour autant'
        );
    }

    /** A chest's closure is a lid, not a doorway. */
    public function testAnOpenChestStillOccupiesItsTile(): void
    {
        $mover = $this->createRealPlayer('GmMarcheur');
        $id = $this->installExemplar('coffre_bois', 4, 4);
        $coordsId = (int) \Classes\View::get_coords_id(
            (object) ['x' => 4, 'y' => 4, 'z' => 0, 'plan' => 'gaia']
        );

        (new BuildingService())->setOpen($id, true);

        $this->assertNotNull(
            (new \App\Service\Map\TileOccupancyService($this->link))
                ->stepRefusal($coordsId, (int) $mover->id, true),
            'un coffre ouvert barre encore : sa fermeture décide du contenu, pas du pas'
        );
    }

    /** Location settles it before the type is consulted. */
    public function testAnInstalledExemplarObstructsAndADroppedOneDoesNot(): void
    {
        $bois = $this->itemOrSkip('bois');
        $player = $this->createRealPlayer('GmPoseur');
        $mover = $this->createRealPlayer('GmPassant2');

        $instanceId = (new \App\Service\ItemInstanceService())->create($player->id, $bois->id, $player->id, '');
        $entityId = (int) $this->link->fetchOne(
            'SELECT entity_id FROM item_instances WHERE id = ?',
            [$instanceId]
        );
        $coordsId = (int) \Classes\View::get_coords_id(
            (object) ['x' => 7, 'y' => 7, 'z' => 0, 'plan' => 'gaia']
        );
        $location = new \App\Service\Map\EntityLocationService($this->link);
        $occupancy = new \App\Service\Map\TileOccupancyService($this->link);

        // « bois » n'obstrue rien au catalogue : posé, on le traverse.
        $location->installOnCell($entityId, $coordsId);
        $this->assertNull(
            $occupancy->stepRefusal($coordsId, (int) $mover->id, true),
            'un objet qui ne bloque pas se traverse, même posé'
        );

        // On lui donne le réglage d'un coffre : posé, il tient sa case.
        $this->link->executeStatement(
            'UPDATE items SET blocks_passage = 1 WHERE id = ?',
            [$bois->id]
        );

        try {
            $this->assertNotNull(
                $occupancy->stepRefusal($coordsId, (int) $mover->id, true),
                'posé et bloquant, il tient sa case'
            );

            $location->dropOnCell($entityId, $coordsId);
            $this->assertNull(
                $occupancy->stepRefusal($coordsId, (int) $mover->id, true),
                'jeté au sol, il n\'obstrue plus rien — la localisation tranche avant le type'
            );
        } finally {
            $this->link->executeStatement(
                'UPDATE items SET blocks_passage = 0 WHERE id = ?',
                [$bois->id]
            );
            }
    }

    /** Un mur ne se ferme pour personne, propriétaire compris. */
    /** Both catalogues share names: 'bronze' is a vein and the ingot from it. */
    public function testAGatheredMaterialDoesNotObstructLikeItsVein(): void
    {
        $offenders = $this->link->fetchFirstColumn(
            "SELECT i.name
               FROM items i
               JOIN races r ON CONVERT(r.name USING utf8mb4) = CONVERT(i.name USING utf8mb4)
              WHERE r.kind = 'structure'
                AND r.structure_nature = 'ressource'
                AND (i.blocks_passage = 1 OR i.blocks_projectiles = 1)"
        );

        $this->assertSame(
            [],
            $offenders,
            'un matériau ne barre ni le pas ni la flèche : ' . implode(', ', $offenders)
        );
    }

    public function testNobodyLocksWhatHasNoDoor(): void
    {
        $owner = $this->createRealPlayer('GmMaçon');
        $id = $this->placeStructure('palissade', 0, 8);
        $this->link->executeStatement('UPDATE players SET owner_id = ? WHERE id = ?', [$owner->id, $id]);

        $this->assertFalse($this->lock()->mayLock($id, (int) $owner->id));
    }
}
