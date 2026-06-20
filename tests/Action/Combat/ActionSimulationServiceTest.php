<?php

namespace Tests\Action\Combat;

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
    private function actionWithComputeCondition(array $params): MeleeAction
    {
        $action = new MeleeAction();
        $condition = new ActionCondition();
        $condition->setConditionType('Compute');
        $condition->setParameters($params);
        $action->addCondition($condition);

        return $action;
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
