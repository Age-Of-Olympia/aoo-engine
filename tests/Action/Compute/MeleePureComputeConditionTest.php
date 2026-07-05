<?php

namespace Tests\Action\Compute;

use App\Action\Condition\ConditionObject;
use App\Action\Condition\MeleePureComputeCondition;
use App\Action\MeleeAction;
use App\Entity\ActionCondition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Action\Mock\PlayerMock;
use Tests\Action\Mock\ScriptedDice;

/**
 * A "Pure" subclass now routes through AbstractComputeCondition::check() and
 * accepts an injected Dice (the seam added in this MR). This locks both: the
 * subclass resolves an opposed roll deterministically.
 */
#[Group('action-combat')]
class MeleePureComputeConditionTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('AUTO_FAIL')) {
            define('AUTO_FAIL', false);
        }
    }

    private function player(int $id, string $name): PlayerMock
    {
        $player = new PlayerMock($id, $name);
        $player->caracs->force = 1;

        return $player;
    }

    private function condition(): ActionCondition
    {
        $action = new MeleeAction();
        $action->setName('attaquer');

        $condition = new ActionCondition();
        $condition->setConditionType('MeleePure');
        $condition->setParameters(['actorRollType' => 'force', 'targetRollType' => 'force']);
        $condition->setAction($action);

        return $condition;
    }

    private function check(ScriptedDice $dice): \App\Action\Condition\ConditionResult
    {
        $condition = $this->condition();
        $conditionObject = new ConditionObject();
        $conditionObject->setAction($condition->getAction());

        return (new MeleePureComputeCondition($dice))->check(
            $this->player(1, 'Actor'),
            $this->player(2, 'Target'),
            $condition,
            $conditionObject,
        );
    }

    public function testActorWinsWhenItsInjectedRollBeatsTheTarget(): void
    {
        $this->assertTrue($this->check(new ScriptedDice([[10], [5]]))->isSuccess());
    }

    public function testActorLosesWhenItsInjectedRollIsLower(): void
    {
        $this->assertFalse($this->check(new ScriptedDice([[5], [10]]))->isSuccess());
    }
}
