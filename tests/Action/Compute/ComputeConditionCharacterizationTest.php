<?php

namespace Tests\Action\Compute;

use App\Action\Condition\ComputeCondition;
use App\Action\Condition\ConditionObject;
use App\Action\OutcomeInstruction\MalusOutcomeInstruction;
use App\Action\MeleeAction;
use App\Entity\ActionCondition;
use App\Entity\ActionPassive;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Action\Mock\PlayerMock;
use Tests\Action\Mock\ScriptedDice;

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

    /**
     * @param array<string, mixed> $extraParams
     */
    private function meleeAttackCondition(array $extraParams = []): ActionCondition
    {
        $action = new MeleeAction();
        $action->setName('attaquer');

        $condition = new ActionCondition();
        $condition->setConditionType('Compute');
        $condition->setParameters(array_merge(
            ['actorRollType' => 'force', 'targetRollType' => 'force'],
            $extraParams
        ));
        $condition->setAction($action);

        return $condition;
    }

    private function check(ScriptedDice $dice, ActionCondition $condition, ?PlayerMock $actor = null, ?PlayerMock $target = null)
    {
        $compute = new ComputeCondition($dice);
        $conditionObject = new ConditionObject();
        $conditionObject->setAction($condition->getAction());

        return $compute->check($actor ?? $this->actor(), $target ?? $this->target(), $condition, $conditionObject);
    }

    public function testActorWinsWhenRollBeatsTarget(): void
    {
        $condition = $this->meleeAttackCondition();

        $result = $this->check(new ScriptedDice([[10], [5]]), $condition);

        $this->assertTrue($result->isSuccess());
        $this->assertCount(0, $condition->getAction()->getAutomaticOutcomeInstructions());
    }

    public function testActorWinsOnTie(): void
    {
        $condition = $this->meleeAttackCondition();

        $result = $this->check(new ScriptedDice([[7], [7]]), $condition);

        $this->assertTrue($result->isSuccess());
    }

    public function testActorLosesAndSchedulesMalus(): void
    {
        $condition = $this->meleeAttackCondition();

        $result = $this->check(new ScriptedDice([[5], [10]]), $condition);

        $this->assertFalse($result->isSuccess());

        $scheduled = $condition->getAction()->getAutomaticOutcomeInstructions();
        $this->assertCount(1, $scheduled);
        $this->assertInstanceOf(MalusOutcomeInstruction::class, $scheduled->first());
    }

    public function testAdvantageUsesHigherOfTwoRolls(): void
    {
        $condition = $this->meleeAttackCondition(['actorAdvantage' => true]);

        $result = $this->check(new ScriptedDice([[5], [12], [10]]), $condition);

        $this->assertTrue($result->isSuccess());
    }

    public function testDisadvantageUsesLowerOfTwoRolls(): void
    {
        $condition = $this->meleeAttackCondition(['actorDisadvantage' => true]);

        $result = $this->check(new ScriptedDice([[12], [5], [10]]), $condition);

        $this->assertFalse($result->isSuccess());
    }

    public function testTargetMalusIsSubtractedFromRoll(): void
    {
        $condition = $this->meleeAttackCondition();

        $target = $this->target();
        $target->data->malus = 3;

        $result = $this->check(new ScriptedDice([[8], [10]]), $condition, null, $target);

        $this->assertTrue($result->isSuccess());
    }

    public function testTargetDefensivePassiveBonusIsApplied(): void
    {
        // Before the id/name fix the target's def passive resolved by name → 0,
        // so this attack succeeded; with the bonus applied the target now wins.
        $passive = new ActionPassive();
        $passive->setId(7);
        $passive->setName('bouclier');
        $passive->setTraits(['force']);
        $passive->setType('def');
        $passive->setCarac('fixed');
        $passive->setValue(0.0);

        $target = $this->target();
        $target->playerPassiveService->passives = [$passive];
        $target->playerPassiveService->computedValue = 100;

        $condition = $this->meleeAttackCondition();
        $result = $this->check(new ScriptedDice([[10], [5]]), $condition, null, $target);

        $this->assertFalse($result->isSuccess());
        $this->assertCount(1, $condition->getAction()->getAutomaticOutcomeInstructions());
    }
}
