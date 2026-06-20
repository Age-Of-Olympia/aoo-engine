<?php

namespace Tests\Action\Schema;

use App\Action\Condition\ConditionRegistry;
use App\Action\MeleeAction;
use App\Entity\ActionCondition;
use App\Service\Action\SimulationFormBuilder;
use App\Service\OutcomeInstructionService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class SimulationFormBuilderTest extends TestCase
{
    private function builder(): SimulationFormBuilder
    {
        // Mock the outcome service (its real constructor touches the entity manager);
        // these actions carry no outcomes, so it is never queried.
        return new SimulationFormBuilder(new ConditionRegistry(), $this->createMock(OutcomeInstructionService::class));
    }

    private function condition(string $type, array $params): ActionCondition
    {
        $condition = new ActionCondition();
        $condition->setConditionType($type);
        $condition->setParameters($params);

        return $condition;
    }

    public function testFieldsAreDerivedFromTheActionsConditions(): void
    {
        $action = new MeleeAction();
        $action->setName('melee');
        $action->addCondition($this->condition('RequiresDistance', ['max' => 1]));
        $action->addCondition($this->condition('RequiresWeaponType', ['type' => ['melee']]));
        $action->addCondition($this->condition('RequiresTraitValue', ['a' => 1]));
        $action->addCondition($this->condition('MeleeCompute', ['actorRollType' => 'cc', 'targetRollType' => 'cc/agi']));

        $ids = array_map(static fn($f): string => $f->side . ':' . $f->kind . ':' . $f->key, $this->builder()->fieldsFor($action));

        $this->assertContains('shared:distance:distance', $ids);
        $this->assertContains('actor:weapon:weapon', $ids);
        $this->assertContains('actor:remaining:a', $ids);
        $this->assertContains('actor:trait:cc', $ids);
        $this->assertContains('target:trait:cc', $ids);
        $this->assertContains('target:trait:agi', $ids);
    }

    public function testMeleeComputeDeclaresBothDefenseTraitsRegardlessOfTargetRollType(): void
    {
        // "Vol à la tire" rolls against agi, but the melee defense reads max(cc, agi):
        // the form must ask for cc too, or the simulated target's cc is unset.
        $action = new MeleeAction();
        $action->setName('steal');
        $action->addCondition($this->condition('MeleeCompute', ['actorRollType' => 'agi', 'targetRollType' => 'agi']));

        $ids = array_map(static fn($f): string => $f->side . ':' . $f->kind . ':' . $f->key, $this->builder()->fieldsFor($action));

        $this->assertContains('target:trait:cc', $ids);
        $this->assertContains('target:trait:agi', $ids);
    }

    public function testAnActionWithNoDeclaringConditionsYieldsNoFields(): void
    {
        $action = new MeleeAction();
        $action->setName('bare');
        $action->addCondition($this->condition('NoBerserk', []));

        $this->assertSame([], $this->builder()->fieldsFor($action));
    }
}
