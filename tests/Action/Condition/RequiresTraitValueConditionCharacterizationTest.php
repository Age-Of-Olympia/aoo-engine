<?php

namespace Tests\Action\Condition;

use App\Action\Condition\ConditionObject;
use App\Action\Condition\RequiresTraitValueCondition;
use App\Action\MeleeAction;
use App\Entity\ActionCondition;
use App\Entity\ActionPassive;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Action\Mock\PlayerMock;

#[Group('action-condition')]
class RequiresTraitValueConditionCharacterizationTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('CARACS')) {
            define('CARACS', ['cc' => 'CC', 'f' => 'F', 'agi' => 'Agi', 'pm' => 'PM', 'mvt' => 'Mvt', 'pa' => 'PA']);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function condition(array $params): ActionCondition
    {
        $action = new MeleeAction();
        $action->setName('attaquer');

        $condition = new ActionCondition();
        $condition->setConditionType('RequiresTraitValue');
        $condition->setParameters($params);
        $condition->setAction($action);

        return $condition;
    }

    private function check(PlayerMock $actor, ActionCondition $condition): \App\Action\Condition\ConditionResult
    {
        $conditionObject = new ConditionObject();
        $conditionObject->setAction($condition->getAction());

        return (new RequiresTraitValueCondition())->check($actor, new PlayerMock(2, 'Target'), $condition, $conditionObject);
    }

    private function passive(string $name): ActionPassive
    {
        $passive = new ActionPassive();
        $passive->setName($name);

        return $passive;
    }

    public function testFlatTraitCostIsAffordableWithEnoughPoints(): void
    {
        $actor = new PlayerMock(1, 'Actor');
        $actor->remaining = ['pm' => 5];

        $this->assertTrue($this->check($actor, $this->condition(['pm' => 3]))->isSuccess());
    }

    public function testFlatTraitCostIsRejectedWithoutEnoughPoints(): void
    {
        $actor = new PlayerMock(1, 'Actor');
        $actor->remaining = ['pm' => 2];

        $result = $this->check($actor, $this->condition(['pm' => 3]));

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('PM', implode(' ', $result->getConditionFailureMessages()));
    }

    public function testRemainingRequiresAtLeastOnePoint(): void
    {
        $actor = new PlayerMock(1, 'Actor');

        $actor->remaining = ['pm' => 1];
        $this->assertTrue($this->check($actor, $this->condition(['remaining' => 'pm']))->isSuccess());

        $actor->remaining = ['pm' => 0];
        $this->assertFalse($this->check($actor, $this->condition(['remaining' => 'pm']))->isSuccess());
    }

    public function testEnergieEntryCostsNothing(): void
    {
        $actor = new PlayerMock(1, 'Actor');
        $actor->remaining = ['pm' => 0];

        $this->assertTrue($this->check($actor, $this->condition(['energie' => 'both']))->isSuccess());

        $charges = (new RequiresTraitValueCondition())->applyCosts($actor, null, $this->condition(['energie' => 'both']));
        $this->assertSame([], $charges);
    }

    public function testMarkerParamWithNonNumericValueCostsNothing(): void
    {
        // {"repos":"effets"} is a marker, not a payable cost: it must not gate the
        // action nor warn on a missing CARACS entry (regression).
        $actor = new PlayerMock(1, 'Actor');

        $this->assertTrue($this->check($actor, $this->condition(['repos' => 'effets']))->isSuccess());

        $charges = (new RequiresTraitValueCondition())->applyCosts($actor, null, $this->condition(['repos' => 'effets']));
        $this->assertSame([], $charges);
    }

    public function testSimulationInputsSkipsMarkerParams(): void
    {
        $fields = RequiresTraitValueCondition::simulationInputs(['a' => 1, 'repos' => 'effets', 'energie' => 'both']);

        $this->assertSame(['a'], array_map(static fn ($f) => $f->key, $fields));
    }

    public function testApplyCostsChargesAFlatTrait(): void
    {
        $actor = new PlayerMock(1, 'Actor');

        $charges = (new RequiresTraitValueCondition())->applyCosts($actor, null, $this->condition(['pm' => 3]));

        $this->assertStringContainsString('3 PM', $charges[0]);
    }

    public function testCostFallsBackToTheNoneDefaultWithoutThePassive(): void
    {
        // The action's PM cost depends on a passive: 5 with "berserk", else the
        // "none" default of 3. Without the passive the cost is 3 in both the
        // affordability check and applyCosts (previously they disagreed).
        $actor = new PlayerMock(1, 'Actor');
        $cost = ['pm' => [['berserk', 5], ['none', 3]]];

        $actor->remaining = ['pm' => 2];
        $this->assertFalse($this->check($actor, $this->condition($cost))->isSuccess());

        $actor->remaining = ['pm' => 3];
        $this->assertTrue($this->check($actor, $this->condition($cost))->isSuccess());

        $charges = (new RequiresTraitValueCondition())->applyCosts($actor, null, $this->condition($cost));
        $this->assertStringContainsString('3 PM', $charges[0]);
    }

    public function testCostUsesThePassiveAmountWhenTheActorHasIt(): void
    {
        $actor = new PlayerMock(1, 'Actor');
        $actor->passivesList = [$this->passive('berserk')];
        $cost = ['pm' => [['berserk', 5], ['none', 3]]];

        $actor->remaining = ['pm' => 4];
        $this->assertFalse($this->check($actor, $this->condition($cost))->isSuccess());

        $actor->remaining = ['pm' => 6];
        $this->assertTrue($this->check($actor, $this->condition($cost))->isSuccess());
    }
}
