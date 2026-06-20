<?php

namespace Tests\Action\Compute;

use App\Action\Condition\ComputePureCondition;
use App\Action\Condition\ConditionObject;
use App\Action\OutcomeInstruction\MalusOutcomeInstruction;
use App\Action\MeleeAction;
use App\Entity\ActionCondition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Action\Mock\PlayerMock;
use Tests\Action\Mock\ScriptedDice;

#[Group('action-combat')]
class ComputePureConditionCharacterizationTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('AUTO_FAIL')) {
            define('AUTO_FAIL', false);
        }
    }

    private function actor(): PlayerMock
    {
        $actor = new PlayerMock(1, 'Actor');
        $actor->caracs->force = 1;
        return $actor;
    }

    private function target(): PlayerMock
    {
        $target = new PlayerMock(2, 'Target');
        $target->caracs->force = 1;
        return $target;
    }

    /**
     * @param array<string, mixed> $extraParams
     */
    private function pureAttackCondition(array $extraParams = []): ActionCondition
    {
        $action = new MeleeAction();
        $action->setName('attaquer');

        $condition = new ActionCondition();
        $condition->setConditionType('ComputePure');
        $condition->setParameters(array_merge(
            ['actorRollType' => 'force', 'targetRollType' => 'force'],
            $extraParams
        ));
        $condition->setAction($action);

        return $condition;
    }

    private function check(ScriptedDice $dice, ActionCondition $condition)
    {
        $compute = new ComputePureCondition($dice);
        $conditionObject = new ConditionObject();
        $conditionObject->setAction($condition->getAction());

        return $compute->check($this->actor(), $this->target(), $condition, $conditionObject);
    }

    public function testActorWinsWhenRollBeatsTarget(): void
    {
        $condition = $this->pureAttackCondition();

        $result = $this->check(new ScriptedDice([[10], [5]]), $condition);

        $this->assertTrue($result->isSuccess());
        $this->assertCount(0, $condition->getAction()->getAutomaticOutcomeInstructions());
    }

    public function testActorWinsOnTie(): void
    {
        $result = $this->check(new ScriptedDice([[7], [7]]), $this->pureAttackCondition());

        $this->assertTrue($result->isSuccess());
    }

    public function testActorLosesAndSchedulesMalus(): void
    {
        $condition = $this->pureAttackCondition();

        $result = $this->check(new ScriptedDice([[5], [10]]), $condition);

        $this->assertFalse($result->isSuccess());

        $scheduled = $condition->getAction()->getAutomaticOutcomeInstructions();
        $this->assertCount(1, $scheduled);
        $this->assertInstanceOf(MalusOutcomeInstruction::class, $scheduled->first());
    }

    public function testAdvantageUsesHigherOfTwoRolls(): void
    {
        $condition = $this->pureAttackCondition(['actorAdvantage' => true]);

        $result = $this->check(new ScriptedDice([[5], [12], [10]]), $condition);

        $this->assertTrue($result->isSuccess());
    }

    public function testDisadvantageUsesLowerOfTwoRolls(): void
    {
        $condition = $this->pureAttackCondition(['actorDisadvantage' => true]);

        $result = $this->check(new ScriptedDice([[12], [5], [10]]), $condition);

        $this->assertFalse($result->isSuccess());
    }
}
