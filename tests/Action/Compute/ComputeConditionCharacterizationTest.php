<?php

namespace Tests\Action\Compute;

use App\Action\Condition\ComputeCondition;
use App\Action\Condition\ConditionObject;
use App\Action\OutcomeInstruction\MalusOutcomeInstruction;
use App\Action\MeleeAction;
use App\Entity\ActionCondition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Action\Mock\PlayerMock;
use Tests\Action\Mock\ScriptedDice;

class TestableComputeCondition extends ComputeCondition
{
    public function setActorRollTrait(string $trait): void
    {
        $this->actorRollTrait = $trait;
    }

    public function setTargetRollTrait(string $trait): void
    {
        $this->targetRollTrait = $trait;
    }

    /**
     * @return array{0: array<int,int>, 1: int, 2: string}
     */
    public function exposeComputeActor($actor, $dice, ConditionObject $conditionObject): array
    {
        return $this->computeActor($actor, $dice, $conditionObject);
    }

    /**
     * @return array{0: array<int,int>, 1: int, 2: string}
     */
    public function exposeComputeTarget($target, $dice, ConditionObject $conditionObject): array
    {
        return $this->computeTarget($target, $dice, $conditionObject);
    }
}

#[Group('action-combat')]
class ComputeConditionCharacterizationTest extends TestCase
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

    private function actorContext(bool $advantage = false, bool $disadvantage = false): ConditionObject
    {
        $conditionObject = new ConditionObject();
        $conditionObject->setActorRollBonus(0);
        $conditionObject->setActorAdvantage($advantage);
        $conditionObject->setActorDisadvantage($disadvantage);
        return $conditionObject;
    }

    private function meleeAttackCondition(): ActionCondition
    {
        $action = new MeleeAction();
        $action->setName('attaquer');

        $condition = new ActionCondition();
        $condition->setConditionType('Compute');
        $condition->setParameters(['actorRollType' => 'force', 'targetRollType' => 'force']);
        $condition->setAction($action);

        return $condition;
    }

    private function checkAttack(ScriptedDice $dice, ActionCondition $condition)
    {
        $compute = new ComputeCondition($dice);
        $conditionObject = new ConditionObject();
        $conditionObject->setAction($condition->getAction());

        return $compute->check($this->actor(), $this->target(), $condition, $conditionObject);
    }

    public function testActorAdvantageKeepsHigherOfTwoRolls(): void
    {
        $condition = new TestableComputeCondition();
        $condition->setActorRollTrait('force');

        [, $total] = $condition->exposeComputeActor(
            $this->actor(),
            new ScriptedDice([[5], [12]]),
            $this->actorContext(advantage: true)
        );

        $this->assertSame(12, $total);
    }

    public function testActorDisadvantageKeepsLowerOfTwoRolls(): void
    {
        $condition = new TestableComputeCondition();
        $condition->setActorRollTrait('force');

        [, $total] = $condition->exposeComputeActor(
            $this->actor(),
            new ScriptedDice([[5], [12]]),
            $this->actorContext(disadvantage: true)
        );

        $this->assertSame(5, $total);
    }

    public function testActorAdvantageAndDisadvantageCancelToSingleRoll(): void
    {
        $condition = new TestableComputeCondition();
        $condition->setActorRollTrait('force');

        [, $total] = $condition->exposeComputeActor(
            $this->actor(),
            new ScriptedDice([[7]]),
            $this->actorContext(advantage: true, disadvantage: true)
        );

        $this->assertSame(7, $total);
    }

    public function testTargetMalusIsSubtractedFromRoll(): void
    {
        $condition = new TestableComputeCondition();
        $condition->setTargetRollTrait('force');

        $target = $this->target();
        $target->data->malus = 5;

        $conditionObject = new ConditionObject();
        $conditionObject->setTargetRollBonus(0);
        $conditionObject->setTargetAdvantage(false);
        $conditionObject->setTargetDisadvantage(false);

        [, $total] = $condition->exposeComputeTarget($target, new ScriptedDice([[10]]), $conditionObject);

        $this->assertSame(5, $total);
    }

    public function testCheckSucceedsWhenActorRollBeatsTarget(): void
    {
        $condition = $this->meleeAttackCondition();

        $result = $this->checkAttack(new ScriptedDice([[10], [5]]), $condition);

        $this->assertTrue($result->isSuccess());
        $this->assertCount(0, $condition->getAction()->getAutomaticOutcomeInstructions());
    }

    public function testCheckSucceedsWhenRollsAreTied(): void
    {
        $condition = $this->meleeAttackCondition();

        $result = $this->checkAttack(new ScriptedDice([[7], [7]]), $condition);

        $this->assertTrue($result->isSuccess());
    }

    public function testCheckFailsAndSchedulesMalusWhenActorRollLoses(): void
    {
        $condition = $this->meleeAttackCondition();

        $result = $this->checkAttack(new ScriptedDice([[5], [10]]), $condition);

        $this->assertFalse($result->isSuccess());

        $scheduled = $condition->getAction()->getAutomaticOutcomeInstructions();
        $this->assertCount(1, $scheduled);
        $this->assertInstanceOf(MalusOutcomeInstruction::class, $scheduled->first());
    }
}
