<?php

namespace Tests\Action\OutcomeInstruction;

use App\Action\Condition\ConditionObject;
use App\Action\OutcomeInstruction\RemoveMalusOutcomeInstruction;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('action-outcome')]
class RemoveMalusOutcomeInstructionCharacterizationTest extends TestCase
{
    private function player(string $name): Player&MockObject
    {
        $player = $this->createMock(Player::class);
        $player->data = (object) ['name' => $name];

        return $player;
    }

    public function testFixedMalusIsUsedWhenNoCarac(): void
    {
        $instruction = new RemoveMalusOutcomeInstruction();

        $this->assertSame(3, $instruction->computeMalusToRemove(3, false, 0.0, 1));
    }

    public function testCaracValueOverridesFixedMalus(): void
    {
        $instruction = new RemoveMalusOutcomeInstruction();

        $this->assertSame(5, $instruction->computeMalusToRemove(3, true, 10.0, 2));
    }

    public function testNoMalusWhenNeitherSet(): void
    {
        $instruction = new RemoveMalusOutcomeInstruction();

        $this->assertSame(0, $instruction->computeMalusToRemove(0, false, 0.0, 1));
    }

    public function testRemovalTargetsTheConfiguredSubject(): void
    {
        $instruction = new RemoveMalusOutcomeInstruction();
        $instruction->setParameters(['fixedMalus' => 3, 'to' => 'actor']);

        $actor = $this->player('Actor');
        $target = $this->player('Target');

        $actor->expects($this->once())->method('put_malus')->with(-3);
        $target->expects($this->never())->method('put_malus');

        $result = $instruction->execute($actor, $target, new ConditionObject());

        // The message must name the subject the malus was removed from, and a
        // failed read of the (always-success) result must not echo success text.
        $this->assertStringContainsString('à Actor.', $result->getOutcomeSuccessMessages()[0]);
        $this->assertSame([], $result->getOutcomeFailureMessages());
    }

    public function testRemovalDefaultsToTheTarget(): void
    {
        $instruction = new RemoveMalusOutcomeInstruction();
        $instruction->setParameters(['fixedMalus' => 3]);

        $actor = $this->player('Actor');
        $target = $this->player('Target');

        $actor->expects($this->never())->method('put_malus');
        $target->expects($this->once())->method('put_malus')->with(-3);

        $result = $instruction->execute($actor, $target, new ConditionObject());

        $this->assertStringContainsString('à Target.', $result->getOutcomeSuccessMessages()[0]);
    }
}
