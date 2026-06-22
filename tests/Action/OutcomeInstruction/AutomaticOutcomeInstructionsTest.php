<?php

namespace Tests\Action\OutcomeInstruction;

use App\Action\MeleeAction;
use App\Action\StealAction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-outcome')]
class AutomaticOutcomeInstructionsTest extends TestCase
{
    public function testAttacksNoLongerAddCodeDefinedAutomatics(): void
    {
        // Adrenaline/object-effect moved to the 'attack' type-level instructions;
        // init no longer seeds anything in code. The collection now only ever holds
        // instructions added dynamically during execution (e.g. a miss malus).
        $action = new MeleeAction();
        $action->initAutomaticOutcomeInstructions();

        $this->assertCount(0, $action->getAutomaticOutcomeInstructions());
    }

    public function testNonAttackActionsHaveNoAutomaticInstructions(): void
    {
        $action = new StealAction();
        $action->initAutomaticOutcomeInstructions();

        $this->assertCount(0, $action->getAutomaticOutcomeInstructions());
    }
}
