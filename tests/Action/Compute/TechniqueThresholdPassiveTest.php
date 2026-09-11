<?php

namespace Tests\Action\Compute;

use App\Action\Condition\ConditionObject;
use App\Action\Condition\TechniqueComputeCondition;
use App\Action\TechniqueAction;
use App\Entity\ActionCondition;
use App\Entity\ActionPassive;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Action\Mock\PlayerMock;
use Tests\Action\Mock\ScriptedDice;

/**
 * A technique at distance needs roll >= 4 × (cells − 1); a 'seuil' passive
 * (retrait) lowers that threshold by its value.
 */
#[Group('action-combat')]
class TechniqueThresholdPassiveTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('AUTO_FAIL')) {
            define('AUTO_FAIL', false);
        }
    }

    private function check(PlayerMock $actor): bool
    {
        $target = new PlayerMock(999999, 'Target');
        $target->caracs->fm = 1;
        $target->coords = (object) ['x' => 3, 'y' => 0, 'z' => 0, 'plan' => 'test_plan'];

        $action = new TechniqueAction();
        $action->setName('technique');
        $condition = new ActionCondition();
        $condition->setConditionType('TechniqueCompute');
        $condition->setParameters(['actorRollType' => 'fm', 'targetRollType' => 'fm']);
        $condition->setAction($action);

        $conditionObject = new ConditionObject();
        $conditionObject->setAction($action);

        // Actor 6 + fm 1 = 7 against target 1 + 1 = 2: only the threshold (8 at three cells) can stop it.
        $compute = new TechniqueComputeCondition(new ScriptedDice([[6], [1]]));

        return $compute->check($actor, $target, $condition, $conditionObject)->isSuccess();
    }

    public function testRollUnderTheDistanceThresholdMisses(): void
    {
        $actor = new PlayerMock(1, 'Actor');
        $actor->caracs->fm = 1;

        $this->assertFalse($this->check($actor));
    }

    public function testSeuilPassiveLowersTheThreshold(): void
    {
        $retrait = new ActionPassive();
        $retrait->setId(1);
        $retrait->setName('retrait');
        $retrait->setTraits(['seuil']);
        $retrait->setType('');
        $retrait->setCarac('fixed');
        $retrait->setValue(4.0);

        $actor = new PlayerMock(1, 'Actor');
        $actor->caracs->fm = 1;
        $actor->passivesList = [$retrait];

        $this->assertTrue($this->check($actor));
    }
}
