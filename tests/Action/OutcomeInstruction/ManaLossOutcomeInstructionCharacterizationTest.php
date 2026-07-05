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

    public function testBackfireMessageNamesTheActorNotTheTarget(): void
    {
        // On a backfire the loss hits the actor; the message used to name the target.
        $instruction = new ManaLossOutcomeInstruction();
        $instruction->setParameters(['lossType' => 'difference']);

        $actor = $this->player('Actor');
        $actor->method('getRemaining')->willReturn(10);
        $actor->method('putBonus')->willReturn(true);
        $target = $this->player('Target');

        $conditionObject = new ConditionObject();
        $conditionObject->setActorRoll(5);
        $conditionObject->setTargetRoll(8);

        $messages = $instruction->execute($actor, $target, $conditionObject)->getOutcomeSuccessMessages();

        $this->assertStringContainsString('à Actor.', $messages[0]);
        $this->assertStringNotContainsString('à Target.', $messages[0]);
    }
}
