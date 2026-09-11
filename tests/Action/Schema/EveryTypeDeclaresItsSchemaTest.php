<?php

namespace Tests\Action\Schema;

use App\Action\Condition\ConditionRegistry;
use App\Action\Schema\ParameterSchema;
use App\Factory\OutcomeInstructionFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The direction is "everything in the schema, nothing hard-coded": a type
 * without a declaration cannot exist. The contract enforces it at class
 * load; this test says it in the suite, for every type the game can run.
 */
#[Group('action-schema')]
class EveryTypeDeclaresItsSchemaTest extends TestCase
{
    public function testEveryRegisteredConditionDeclaresItsParameters(): void
    {
        $registry = new ConditionRegistry();
        foreach ($registry->getTypes() as $type) {
            $condition = $registry->getCondition($type);
            $this->assertNotNull($condition, $type);
            $this->assertInstanceOf(ParameterSchema::class, $condition::parameterSchema(), $type);
        }
    }

    public function testEveryOutcomeInstructionDeclaresItsParameters(): void
    {
        foreach (OutcomeInstructionFactory::typeMap() as $type => $class) {
            $this->assertInstanceOf(ParameterSchema::class, $class::parameterSchema(), $type);
        }
    }
}
