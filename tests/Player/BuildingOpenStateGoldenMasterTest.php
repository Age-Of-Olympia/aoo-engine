<?php

namespace Tests\Player;

use App\Entity\BuildingDetails;
use App\Service\BuildingService;
use App\Service\RaceService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Ouverture d'un bâtiment (buildings.is_open + BuildingService::
 * closureReason, source unique) : un ÉDIFICE a une porte — fermé
 * d'office en ruine, en construction ou endommagé sous le seuil,
 * fermable volontairement — et un bâtiment fermé tait son dialogue
 * (observe.php). Un OBSTACLE (mur construit, races.structure_nature)
 * n'a pas de porte. L'inactivité, elle, est réservée aux joueurs
 * réels : une entité structure n'est jamais « inactive ».
 */
#[Group('entities-golden-master')]
#[Group('entities-structure')]
class BuildingOpenStateGoldenMasterTest extends LegacyPlayerFixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBuildingsOrSkip();

        try {
            $this->link->executeQuery('SELECT is_open FROM buildings LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('buildings.is_open unavailable (run migrations): ' . $e->getMessage());
        }
    }

    public function testClosureReasonMatrix(): void
    {
        $service = new BuildingService();
        $details = (new BuildingDetails())->setBuildState(BuildingDetails::STATE_BUILT);

        $this->assertNull($service->closureReason($details, 100), 'construit, PV pleins, ouvert => ouvert');
        $this->assertNull(
            $service->closureReason($details, BuildingService::CLOSED_BELOW_PV_PCT),
            'au seuil exactement, encore ouvert'
        );
        $this->assertSame('endommagé', $service->closureReason($details, BuildingService::CLOSED_BELOW_PV_PCT - 1));

        $details->setIsOpen(false);
        $this->assertSame('fermé volontairement', $service->closureReason($details, 100));

        // Les états priment sur la fermeture volontaire et les dégâts.
        $details->setBuildState(BuildingDetails::STATE_RUIN);
        $this->assertSame('en ruine', $service->closureReason($details, 0));

        $details->setBuildState(BuildingDetails::STATE_CONSTRUCTION);
        $this->assertSame('en construction', $service->closureReason($details, 100));
    }

    public function testSetOpenPersistsAndRejectsNonBuildings(): void
    {
        $service = new BuildingService();
        $id = $this->placeStructure('palissade', 0, 3);

        $this->assertTrue($service->getDetails($id)?->isOpen(), 'posé ouvert par défaut');

        $service->setOpen($id, false);
        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT is_open FROM buildings WHERE player_id = ?', [$id])
        );

        $service->setOpen($id, true);
        $this->assertSame(
            1,
            (int) $this->link->fetchOne('SELECT is_open FROM buildings WHERE player_id = ?', [$id])
        );

        $character = $this->createRealPlayer('GmDoor');
        try {
            $service->setOpen((int) $character->id, false);
            $this->fail('un personnage n\'a pas de porte de bâtiment');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('bâtiment', $e->getMessage());
        }
    }

    public function testStructureNatureSeparatesEdificesFromObstacles(): void
    {
        $raceService = new RaceService();

        $palissade = $raceService->getRaceByName('palissade');
        if ($palissade === null) {
            $this->markTestSkipped("structure type 'palissade' not seeded.");
        }
        $this->assertSame('obstacle', $palissade->getStructureNature(), 'un mur construit est un obstacle');
        $this->assertFalse($palissade->isEdifice(), 'un obstacle n\'a pas de porte');

        $nain = $raceService->getRaceByName('nain');
        $this->assertNotNull($nain);
        $this->assertFalse($nain->isEdifice(), 'une race de personnage n\'est jamais un édifice');
    }

    public function testAStructureEntityIsNeverInactive(): void
    {
        $id = $this->placeStructure('palissade', 0, 3);

        $building = \App\Factory\PlayerFactory::legacy($id);
        $building->get_data();

        $this->assertFalse(
            (bool) $building->data->isInactive,
            "l'inactivité (dernière connexion) est réservée aux joueurs réels — une structure n'est jamais « inactive »"
        );
    }
}
