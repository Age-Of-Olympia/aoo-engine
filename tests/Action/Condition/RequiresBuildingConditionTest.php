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
        [$x, $y] = $this->farTile();
        $actor = $this->actorAt($x, $y);
        $this->placeStructure('palissade', $x, $y + 1);

        $result = $this->checkFor($actor, ['types' => 'palissade', 'range' => 1]);

        $this->assertTrue($result->isSuccess(), 'an open building of the named type, adjacent, passes');
    }

    public function testAnEmptyTypeListAcceptsAnyBuilding(): void
    {
        $this->requireBuildingsOrSkip();
        [$x, $y] = $this->farTile();
        $actor = $this->actorAt($x, $y);
        $this->placeStructure('palissade', $x, $y + 1);

        $this->assertTrue($this->checkFor($actor, [])->isSuccess());
    }

    public function testRefusesWhenNothingStandsInRange(): void
    {
        $this->requireBuildingsOrSkip();
        [$x, $y] = $this->farTile();
        $actor = $this->actorAt($x, $y);

        $result = $this->checkFor($actor, ['types' => 'palissade', 'range' => 1]);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Il faut être près de', implode(' ', $result->getConditionFailureMessages()));
    }

    public function testTheTypeFilterRefusesAnotherBuilding(): void
    {
        $this->requireBuildingsOrSkip();
        [$x, $y] = $this->farTile();
        $actor = $this->actorAt($x, $y);
        $this->placeStructure('palissade', $x, $y + 1);

        $result = $this->checkFor($actor, ['types' => 'taverne', 'range' => 1]);

        $this->assertFalse($result->isSuccess(), 'a palissade is not the taverne the gesture asks for');
    }

    public function testTheRangeIsChebyshevOverTheBuildingCells(): void
    {
        $this->requireBuildingsOrSkip();
        [$x, $y] = $this->farTile();
        $actor = $this->actorAt($x, $y);
        $this->placeStructure('palissade', $x, $y + 2);

        $this->assertFalse($this->checkFor($actor, ['types' => 'palissade', 'range' => 1])->isSuccess());
        $this->assertTrue($this->checkFor($actor, ['types' => 'palissade', 'range' => 2])->isSuccess());
    }

    public function testAShutBuildingRefusesWithItsReason(): void
    {
        $this->requireBuildingsOrSkip();
        $this->sowStructureType('taverne');
        [$x, $y] = $this->farTile();
        $actor = $this->actorAt($x, $y);
        $id = $this->placeStructure('taverne', $x + 1, $y + 1);
        (new BuildingService())->markDestroyed($id);

        $result = $this->checkFor($actor, ['types' => 'taverne', 'range' => 1]);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('en ruine', implode(' ', $result->getConditionFailureMessages()));
    }
}
