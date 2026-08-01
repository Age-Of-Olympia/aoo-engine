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
            $this->link->executeQuery('SELECT is_open FROM players LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('players.is_open unavailable (run migrations): ' . $e->getMessage());
        }
    }

    /**
     * La fermeture volontaire se lit sur l'ENTITÉ : le cas a donc besoin d'une
     * vraie ligne, là où un satellite détaché suffisait. C'est le prix de la
     * règle qui vaudra pour un coffre — lui n'aura jamais de satellite.
     */
    public function testClosureReasonMatrix(): void
    {
        $service = new BuildingService();
        /* Un COFFRE, désormais : une palissade n'a pas de porte, et la
         * fermeture volontaire ne se dit que de ce qui peut se fermer. */
        $id = $this->installExemplar('coffre_bois', 0, 6);
        $details = (new BuildingDetails())->setBuildState(BuildingDetails::STATE_BUILT);

        $this->assertNull($service->closureReason($id, $details, 100), 'construit, PV pleins, ouvert => ouvert');
        $this->assertNull(
            $service->closureReason($id, $details, BuildingService::CLOSED_BELOW_PV_PCT),
            'au seuil exactement, encore ouvert'
        );
        $this->assertSame(
            'endommagé',
            $service->closureReason($id, $details, BuildingService::CLOSED_BELOW_PV_PCT - 1)
        );

        $service->setOpen($id, false);
        $this->assertSame('fermé volontairement', $service->closureReason($id, $details, 100));

        // Les états priment sur la fermeture volontaire et les dégâts.
        $details->setBuildState(BuildingDetails::STATE_RUIN);
        $this->assertSame('en ruine', $service->closureReason($id, $details, 0));

        $details->setBuildState(BuildingDetails::STATE_CONSTRUCTION);
        $this->assertSame('en construction', $service->closureReason($id, $details, 100));
    }

    /** What shuts is what has a door, not what is a building. */
    public function testSetOpenPersistsAndRejectsWhatHasNoDoor(): void
    {
        $service = new BuildingService();
        $id = $this->installExemplar('coffre_bois', 0, 3);

        $this->assertSame(
            1,
            (int) $this->link->fetchOne('SELECT is_open FROM players WHERE id = ?', [$id]),
            'posé ouvert par défaut'
        );

        $service->setOpen($id, false);
        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT is_open FROM players WHERE id = ?', [$id])
        );

        $service->setOpen($id, true);
        $this->assertSame(
            1,
            (int) $this->link->fetchOne('SELECT is_open FROM players WHERE id = ?', [$id])
        );

        $character = $this->createRealPlayer('GmDoor');
        try {
            $service->setOpen((int) $character->id, false);
            $this->fail('un personnage n\'a pas de porte');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('se ferment', $e->getMessage());
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
