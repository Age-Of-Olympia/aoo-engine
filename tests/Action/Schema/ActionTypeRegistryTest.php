<?php

namespace Tests\Action\Schema;

use App\Action\MeleeAction;
use App\Action\StealAction;
use App\Service\Action\ActionTypeRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ActionTypeRegistryTest extends TestCase
{
    public function testAssignableTypesIncludeConcreteAndAbstractGroupingTypes(): void
    {
        $types = (new ActionTypeRegistry())->assignableTypes();

        $this->assertArrayHasKey('attack', $types);   // abstract grouping parent
        $this->assertArrayHasKey('melee', $types);    // concrete
        $this->assertArrayHasKey('steal', $types);
        $this->assertArrayNotHasKey('', $types);      // the Action root is excluded
    }

    public function testActionInheritsItsConcreteThenAbstractTypeKeys(): void
    {
        // MeleeAction extends AttackAction extends Action.
        $keys = (new ActionTypeRegistry())->typeKeysForAction(new MeleeAction());

        $this->assertSame(['melee', 'attack'], $keys);
    }

    public function testActionWithNoAbstractParentResolvesToItselfOnly(): void
    {
        // StealAction extends Action directly.
        $keys = (new ActionTypeRegistry())->typeKeysForAction(new StealAction());

        $this->assertSame(['steal'], $keys);
    }
}
