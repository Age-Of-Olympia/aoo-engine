<?php

namespace Tests\Action\Combat;

use App\Action\Condition\DistanceComputeCondition;
use App\Action\Condition\MeleeComputeCondition;
use App\Action\MeleeAction;
use App\Entity\ActionCondition;
use App\Service\Action\ActionSimulationService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-combat')]
class ActionSimulationServiceTest extends TestCase
{
    /**
     * @param array<string, mixed> $params
     */
    private function actionWithComputeCondition(array $params, string $type = 'Compute'): MeleeAction
    {
        $action = new MeleeAction();
        $condition = new ActionCondition();
        $condition->setConditionType($type);
        $condition->setParameters($params);
        $action->addCondition($condition);

        return $action;
    }

    public function testMeleeDefenseUsesMaxOfCcAndAgi(): void
    {
        $this->assertSame(8, MeleeComputeCondition::targetDefenseValue(5, 8));
    }

    public function testDistanceDefenseUsesTheWeightedBlend(): void
    {
        // floor(max(3/4*8 + 1/4*4, 1/4*8 + 3/4*4)) = floor(max(7, 5)) = 7
        $this->assertSame(7, DistanceComputeCondition::targetDefenseValue(8, 4));
    }

    public function testDistanceMalusAndThresholdFormulas(): void
    {
        $this->assertSame(0, DistanceComputeCondition::distanceMalusFor(2));
        $this->assertSame(3, DistanceComputeCondition::distanceMalusFor(4));
        $this->assertSame(10, DistanceComputeCondition::distanceThresholdFor(4));
    }

    public function testDistanceSimulationAppliesBlendMalusAndThreshold(): void
    {
        $action = $this->actionWithComputeCondition(['actorRollType' => 'ct', 'targetRollType' => 'cc/agi'], 'DistanceCompute');

        $sim = (new ActionSimulationService())->simulateRoll(
            $action,
            ['ct' => 10],
            ['cc' => 4, 'agi' => 8],
            forcedActorRoll: 20,
            forcedTargetRoll: 6,
            distance: 4,
        );

        $this->assertNotNull($sim);
        $this->assertSame(3, $sim->distanceMalus);
        $this->assertSame(10, $sim->distanceThreshold);
        $this->assertSame(17, $sim->actorTotal);
        $this->assertSame(7, $sim->targetTraitValue);
        $this->assertTrue($sim->reachedThreshold);
        $this->assertTrue($sim->hit);
    }

    public function testDistanceShotThatFallsShortOfThresholdMisses(): void
    {
        $action = $this->actionWithComputeCondition(['actorRollType' => 'ct', 'targetRollType' => 'cc/agi'], 'DistanceCompute');

        $sim = (new ActionSimulationService())->simulateRoll(
            $action,
            ['ct' => 10],
            ['cc' => 1, 'agi' => 1],
            forcedActorRoll: 8,
            forcedTargetRoll: 1,
            distance: 4,
        );

        $this->assertNotNull($sim);
        $this->assertFalse($sim->reachedThreshold);
        $this->assertFalse($sim->hit);
    }

    public function testSimulatesOpposedRollWithForcedRolls(): void
    {
        $action = $this->actionWithComputeCondition([
            'actorRollType' => 'cc',
            'targetRollType' => 'agi',
            'actorRollBonus' => 2,
        ]);

        $sim = (new ActionSimulationService())->simulateRoll(
            $action,
            ['cc' => 10],
            ['agi' => 5],
            forcedActorRoll: 6,
            forcedTargetRoll: 6,
        );

        $this->assertNotNull($sim);
        $this->assertSame(10, $sim->actorTraitValue);
        $this->assertSame(8, $sim->actorTotal);
        $this->assertSame(6, $sim->targetTotal);
        $this->assertTrue($sim->hit);
    }

    public function testMissWhenActorTotalBelowTarget(): void
    {
        $action = $this->actionWithComputeCondition(['actorRollType' => 'cc', 'targetRollType' => 'agi']);

        $sim = (new ActionSimulationService())->simulateRoll($action, ['cc' => 1], ['agi' => 1], forcedActorRoll: 3, forcedTargetRoll: 9);

        $this->assertNotNull($sim);
        $this->assertFalse($sim->hit);
    }

    public function testTargetTraitPairTakesTheMaximum(): void
    {
        $action = $this->actionWithComputeCondition(['actorRollType' => 'cc', 'targetRollType' => 'cc/agi']);

        $sim = (new ActionSimulationService())->simulateRoll($action, ['cc' => 5], ['cc' => 3, 'agi' => 9], forcedActorRoll: 5, forcedTargetRoll: 5);

        $this->assertNotNull($sim);
        $this->assertSame(9, $sim->targetTraitValue);
    }

    public function testReturnsNullWhenActionHasNoComputeCondition(): void
    {
        $this->assertNull((new ActionSimulationService())->simulateRoll(new MeleeAction(), [], []));
    }
}
