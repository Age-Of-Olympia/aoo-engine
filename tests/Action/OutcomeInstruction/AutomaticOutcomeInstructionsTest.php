<?php

namespace Tests\Action\OutcomeInstruction;

use App\Action\MeleeAction;
use App\Action\StealAction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-outcome')]
class AutomaticOutcomeInstructionsTest extends TestCase
{
    public function testInitIsIdempotentAndDoesNotDuplicate(): void
    {
        // The workbench previews automatics (one init) and a simulation then
        // re-initialises (a second init) on the same action — this must not
        // double the adrenaline/objectEffect instructions.
        $action = new MeleeAction();
        $action->initAutomaticOutcomeInstructions();
        $countAfterFirst = $action->getAutomaticOutcomeInstructions()->count();

        $action->initAutomaticOutcomeInstructions();

        $this->assertGreaterThan(0, $countAfterFirst);
        $this->assertSame($countAfterFirst, $action->getAutomaticOutcomeInstructions()->count());
    }

    public function testNonAttackActionsHaveNoAutomaticInstructions(): void
    {
        $action = new StealAction();
        $action->initAutomaticOutcomeInstructions();

        $this->assertCount(0, $action->getAutomaticOutcomeInstructions());
    }
}
