<?php

namespace Tests\Action\OutcomeInstruction;

use App\Action\Condition\ConditionObject;
use App\Action\OutcomeInstruction\ManaLossOutcomeInstruction;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('action-outcome')]
class ManaLossOutcomeInstructionCharacterizationTest extends TestCase
{
    private function player(string $name): Player&MockObject
    {
        $player = $this->createMock(Player::class);
        $player->data = (object) ['name' => $name];

        return $player;
    }

    public function testDifferenceIsActorMinusTarget(): void
    {
        $instruction = new ManaLossOutcomeInstruction();

        $this->assertSame(['loss' => 6, 'backfire' => false], $instruction->computeManaLossDifference(10, 4));
    }

    public function testNegativeDifferenceBackfiresWithAbsoluteLoss(): void
    {
        $instruction = new ManaLossOutcomeInstruction();

        $this->assertSame(['loss' => 6, 'backfire' => true], $instruction->computeManaLossDifference(4, 10));
    }

    public function testPmSpillHalvesOverflowIntoPvAndQuartersIntoMalus(): void
    {
        $instruction = new ManaLossOutcomeInstruction();

        $this->assertSame(['pv' => 3, 'malus' => 1], $instruction->computePmSpill(10, 4));
    }

    public function testPmSpillRoundsDown(): void
    {
        $instruction = new ManaLossOutcomeInstruction();

        $this->assertSame(['pv' => 0, 'malus' => 0], $instruction->computePmSpill(5, 4));
    }

    public function testExhaustedPmSpillsIntoPv(): void
    {
        $instruction = new ManaLossOutcomeInstruction();
        $instruction->setParameters(['lossType' => 'fixed', 'value' => 10]);

        $target = $this->player('Target');
        $target->method('getRemaining')->willReturn(4);

        $bonusCalls = [];
        $target->method('putBonus')->willReturnCallback(function ($bonus) use (&$bonusCalls) {
            $bonusCalls[] = $bonus;
            return true;
        });

        $instruction->execute($this->player('Actor'), $target, new ConditionObject());

        $this->assertContains(['pm' => -4], $bonusCalls);
        $this->assertContains(['pv' => -3], $bonusCalls);
    }
}
