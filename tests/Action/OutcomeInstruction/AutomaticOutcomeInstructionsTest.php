<?php

namespace Tests\Action\OutcomeInstruction;

use App\Action\MeleeAction;
use App\Action\StealAction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-outcome')]
class AutomaticOutcomeInstructionsTest extends TestCase
{
    public function testAttackActionsExposeTheirCodeDefinedAutomaticInstructions(): void
    {
        // The workbench enumerates these on a throwaway instance to display them.
        $action = new MeleeAction();
        $action->initAutomaticOutcomeInstructions();

        $this->assertGreaterThan(0, $action->getAutomaticOutcomeInstructions()->count());
    }

    public function testNonAttackActionsHaveNoAutomaticInstructions(): void
    {
        $action = new StealAction();
        $action->initAutomaticOutcomeInstructions();

        $this->assertCount(0, $action->getAutomaticOutcomeInstructions());
    }
}
