<?php

namespace Tests\Action\OutcomeInstruction;

use App\Action\MeleeAction;
use App\Action\StealAction;
use App\Entity\Action;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-outcome')]
class AutomaticOutcomeInstructionsTest extends TestCase
{
    /**
     * @return array<string, array{Action}>
     */
    public static function actions(): array
    {
        return [
            'attack' => [new MeleeAction()],
            'non-attack' => [new StealAction()],
        ];
    }

    #[DataProvider('actions')]
    public function testInitAddsNoCodeDefinedAutomaticInstructions(Action $action): void
    {
        $action->initAutomaticOutcomeInstructions();

        $this->assertCount(0, $action->getAutomaticOutcomeInstructions());
    }
}
