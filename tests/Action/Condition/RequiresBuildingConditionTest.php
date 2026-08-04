<?php

namespace Tests\Action\Condition;

use App\Action\Condition\ConditionObject;
use App\Action\Condition\ConditionResult;
use App\Action\Condition\RequiresBuildingCondition;
use App\Action\CraftAction;
use App\Entity\ActionCondition;
use App\Service\BuildingService;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The gesture happens AT a building: open and in range passes, absent
 * refuses by naming what is missing, shut refuses by naming why.
 */
#[Group('action-condition')]
class RequiresBuildingConditionTest extends LegacyPlayerFixtureTestCase
{
    /** @param array<string, mixed> $params */
    private function checkFor(Player $actor, array $params): ConditionResult
    {
        $action = new CraftAction();
        $action->setName('fabriquer');
        $condition = (new ActionCondition())->setParameters($params)->setAction($action);

        return (new RequiresBuildingCondition())->check($actor, $actor, $condition, new ConditionObject());
    }

    private function actorAt(int $x, int $y): Player
    {
        $actor = $this->createRealPlayer('GmMason');
        $this->movePlayerTo($actor->id, $x, $y);
        $actor->getCoords();
        $actor->get_caracs();

        return $actor;
    }

    public function testPassesBesideAnOpenBuildingOfTheType(): void
    {
        $this->requireBuildingsOrSkip();
        $actor = $this->actorAt(40, 40);
        $this->placeStructure('palissade', 40, 41);

        $result = $this->checkFor($actor, ['types' => 'palissade', 'range' => 1]);

        $this->assertTrue($result->isSuccess(), 'an open building of the named type, adjacent, passes');
    }

    public function testAnEmptyTypeListAcceptsAnyBuilding(): void
    {
        $this->requireBuildingsOrSkip();
        $actor = $this->actorAt(40, 40);
        $this->placeStructure('palissade', 40, 41);

        $this->assertTrue($this->checkFor($actor, [])->isSuccess());
    }

    public function testRefusesWhenNothingStandsInRange(): void
    {
        $this->requireBuildingsOrSkip();
        $actor = $this->actorAt(44, 44);

        $result = $this->checkFor($actor, ['types' => 'palissade', 'range' => 1]);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Il faut être près de', implode(' ', $result->getConditionFailureMessages()));
    }

    public function testTheTypeFilterRefusesAnotherBuilding(): void
    {
        $this->requireBuildingsOrSkip();
        $actor = $this->actorAt(40, 40);
        $this->placeStructure('palissade', 40, 41);

        $result = $this->checkFor($actor, ['types' => 'taverne', 'range' => 1]);

        $this->assertFalse($result->isSuccess(), 'a palissade is not the taverne the gesture asks for');
    }

    public function testTheRangeIsChebyshevOverTheBuildingCells(): void
    {
        $this->requireBuildingsOrSkip();
        $actor = $this->actorAt(40, 40);
        $this->placeStructure('palissade', 40, 42);

        $this->assertFalse($this->checkFor($actor, ['types' => 'palissade', 'range' => 1])->isSuccess());
        $this->assertTrue($this->checkFor($actor, ['types' => 'palissade', 'range' => 2])->isSuccess());
    }

    public function testAShutBuildingRefusesWithItsReason(): void
    {
        $this->requireBuildingsOrSkip();
        $actor = $this->actorAt(40, 40);
        $id = $this->placeStructure('taverne', 41, 41);
        (new BuildingService())->markDestroyed($id);

        $result = $this->checkFor($actor, ['types' => 'taverne', 'range' => 1]);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('en ruine', implode(' ', $result->getConditionFailureMessages()));
    }
}
