<?php

namespace Tests\Action\Schema;

use App\Action\Schema\ActionSchemaCatalog;
use App\Action\Schema\TechnicalLabels;
use App\Service\Action\ActionTypeRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/** A new condition, instruction or action type gets its admin label in the same change. */
#[Group('action-schema')]
class TechnicalLabelsTest extends TestCase
{
    public function testEveryConditionTypeHasALabel(): void
    {
        foreach ((new ActionSchemaCatalog())->allConditionTypes() as $type) {
            $this->assertNotSame($type, TechnicalLabels::condition($type), "Condition sans libellé : $type");
        }
    }

    public function testEveryOutcomeInstructionHasALabel(): void
    {
        foreach ((new ActionSchemaCatalog())->allOutcomeInstructionTypes() as $type) {
            $this->assertNotSame($type, TechnicalLabels::instruction($type), "Instruction sans libellé : $type");
        }
    }

    public function testEveryActionTypeHasALabel(): void
    {
        foreach (array_keys((new ActionTypeRegistry())->assignableTypes()) as $key) {
            $this->assertNotSame($key, TechnicalLabels::actionType($key), "Type d'action sans libellé : $key");
        }
    }
}
