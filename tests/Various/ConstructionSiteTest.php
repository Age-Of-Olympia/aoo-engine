<?php

namespace Tests\Various;

use App\Factory\ActionFactory;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use App\Service\BuildingService;
use App\Service\ConstructionSiteService;
use App\Service\RecipeService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Building takes time: a type declaring work (races.build_work > 0) is
 * born a SITE — shut, at minimal PV — and `travailler` raises it
 * gesture by gesture until the last stone makes it the building. Types
 * declaring nothing keep rising in one gesture, unchanged.
 */
#[Group('items-baseline')]
class ConstructionSiteTest extends LegacyPlayerFixtureTestCase
{
    /** A building's life: its type's pv plus the players_bonus deficit. */
    private function buildingPv(int $id): int
    {
        $max = $this->buildingMaxPv($id);
        $deficit = (int) $this->link->fetchOne(
            "SELECT COALESCE(SUM(n), 0) FROM players_bonus WHERE player_id = ? AND name = 'pv'",
            [$id]
        );

        return $max + $deficit;
    }

    private function buildingMaxPv(int $id): int
    {
        return (int) $this->link->fetchOne(
            'SELECT r.pv FROM players p JOIN races r ON r.name = p.race WHERE p.id = ?',
            [$id]
        );
    }

    private function travaillerOrSkip(): \App\Interface\ActionInterface
    {
        $action = ActionFactory::getAction('travailler');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no 'travailler' row — run migrations).");
        }

        return $action;
    }

    public function testATypeDeclaringWorkIsBornAShutSiteAtMinimalPv(): void
    {
        $this->requireBuildingsOrSkip();
        $id = $this->placeStructure('atelier', 70, 70, asConstructionSite: true);

        $service = new ConstructionSiteService();
        $this->assertSame(['done' => 0, 'total' => 40], $service->progressOf($id));
        $this->assertSame(
            'construction',
            (string) $this->link->fetchOne('SELECT build_state FROM buildings WHERE player_id = ?', [$id])
        );
        $this->assertSame(1, $this->buildingPv($id), 'the site starts at the PV floor');

        $buildingService = new BuildingService();
        $this->assertSame(
            'en construction',
            $buildingService->closureReason($id, $buildingService->getDetails($id), 1)
        );

        // The admin dashboard sees the site's progress on its row.
        $rows = array_filter($buildingService->listBuildings(), static fn (array $b): bool => $b['id'] === $id);
        $row = array_values($rows)[0] ?? null;
        $this->assertNotNull($row);
        $this->assertSame(0, $row['site_done']);
        $this->assertSame(40, $row['site_total']);

        // Shut means shut everywhere: an atelier mid-build crafts nothing.
        $nearby = $buildingService->openBuildingNearby(
            (object) ['x' => 70, 'y' => 71, 'z' => 0, 'plan' => 'gaia'],
            ['atelier'],
            RecipeService::WORKSHOP_RANGE
        );
        $this->assertNull($nearby['open']);
        $this->assertSame('en construction', $nearby['shut']);
    }

    public function testTravaillerAdvancesTheSiteAndItsPv(): void
    {
        $this->requireBuildingsOrSkip();
        $id = $this->placeStructure('atelier', 72, 72, asConstructionSite: true);

        $worker = $this->createRealPlayer('GmCharpentier');
        $this->movePlayerTo($worker->id, 72, 73);
        $worker->getCoords();
        $worker->get_caracs();

        $target = PlayerFactory::legacy($id);
        $target->get_data();
        $target->get_caracs();

        $results = (new ActionExecutorService($this->travaillerOrSkip(), $worker, $target))->executeAction();

        $blockedWhy = [];
        foreach ($results->getConditionsResultsArray() as $conditionResult) {
            $blockedWhy = array_merge($blockedWhy, $conditionResult->getConditionFailureMessages());
        }
        $this->assertFalse($results->isBlocked(), 'blocked: ' . implode(' | ', $blockedWhy));
        $this->assertTrue($results->isSuccess());
        $this->assertSame(['done' => 1, 'total' => 40], (new ConstructionSiteService())->progressOf($id));
        $this->assertSame(3, $this->buildingPv($id), 'PV follow the work: floor(150 × 1/40) — the 2×2 emprise quadruples the work');
    }

    public function testAStrangerMayNotWorkOnAnOwnedSite(): void
    {
        $this->requireBuildingsOrSkip();
        $owner = $this->createRealPlayer('GmProprio');
        $id = (new BuildingService())->place(
            'atelier',
            (object) ['x' => 74, 'y' => 74, 'z' => 0, 'plan' => 'gaia'],
            $owner->id,
            asConstructionSite: true
        );
        $this->trackEntityId($id);

        $stranger = $this->createRealPlayer('GmPassant');
        $this->movePlayerTo($stranger->id, 74, 75);
        $stranger->getCoords();
        $stranger->get_caracs();

        $target = PlayerFactory::legacy($id);
        $target->get_data();
        $target->get_caracs();

        $results = (new ActionExecutorService($this->travaillerOrSkip(), $stranger, $target))->executeAction();

        $this->assertTrue($results->isBlocked(), 'the mayLock rule bars a stranger from an owned site');
        $this->assertSame(['done' => 0, 'total' => 40], (new ConstructionSiteService())->progressOf($id));
    }

    public function testTheLastStoneMakesTheBuilding(): void
    {
        $this->requireBuildingsOrSkip();
        $id = $this->placeStructure('atelier', 76, 76, asConstructionSite: true);

        $service = new ConstructionSiteService();
        $service->advance($id, 39);
        $this->assertSame(['done' => 39, 'total' => 40], $service->progressOf($id));

        $final = $service->advance($id, 1);
        $this->assertTrue($final['completed']);
        $this->assertNull($service->progressOf($id), 'the satellite row goes with the completed site');
        $this->assertSame(
            'built',
            (string) $this->link->fetchOne('SELECT build_state FROM buildings WHERE player_id = ?', [$id])
        );
        $this->assertSame($this->buildingMaxPv($id), $this->buildingPv($id), 'the last stone brings full PV');

        $buildingService = new BuildingService();
        $this->assertNull($buildingService->closureReason($id, $buildingService->getDetails($id), 100));
    }

    public function testAWorkGestureMendsBattleDamageUpToTheFloor(): void
    {
        $this->requireBuildingsOrSkip();
        $id = $this->placeStructure('atelier', 78, 78, asConstructionSite: true);

        $service = new ConstructionSiteService();
        $service->advance($id, 5);
        $this->link->executeStatement(
            "UPDATE players_bonus SET n = ? WHERE player_id = ? AND name = 'pv'",
            [10 - $this->buildingMaxPv($id), $id]
        );

        $service->advance($id, 1);
        $this->assertSame(22, $this->buildingPv($id), 'work rebuilds the fabric: back to floor(150 × 6/40)');
    }

    public function testWorkScalesWithTheFootprint(): void
    {
        $this->requireBuildingsOrSkip();
        $footprints = new \App\Service\Map\EntityTypeFootprintService();
        $footprints->declare('atelier', 3, 1, [[0, 0], [1, 0], [2, 0]]);
        try {
            $id = $this->placeStructure('atelier', 82, 82, asConstructionSite: true);

            $this->assertSame(
                ['done' => 0, 'total' => 30],
                (new ConstructionSiteService())->progressOf($id),
                'the type declares work per cell: three cells, thirty gestures'
            );
        } finally {
            // The migration's 2×2 floor, restored for the other cases.
            $footprints->declare('atelier', 2, 2, [[0, 0], [1, 0], [0, -1], [1, -1]]);
        }
    }

    public function testATypeDeclaringNothingStillRisesInOneGesture(): void
    {
        $this->requireBuildingsOrSkip();
        $id = $this->placeStructure('palissade', 80, 80);

        $this->assertNull((new ConstructionSiteService())->progressOf($id), 'build_work 0: no site, built at once');
        $this->assertSame(
            'built',
            (string) $this->link->fetchOne('SELECT build_state FROM buildings WHERE player_id = ?', [$id])
        );
    }
}
