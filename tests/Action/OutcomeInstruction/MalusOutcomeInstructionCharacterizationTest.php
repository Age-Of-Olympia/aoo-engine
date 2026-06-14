<?php

namespace Tests\Action\OutcomeInstruction;

use App\Action\Condition\ConditionObject;
use App\Action\OutcomeInstruction\MalusOutcomeInstruction;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Action\Mock\PassiveServiceStub;

#[Group('action-outcome')]
class MalusOutcomeInstructionCharacterizationTest extends TestCase
{
    private function player(string $name, PassiveServiceStub $passives): Player&MockObject
    {
        $player = $this->createMock(Player::class);
        $player->data = (object) ['name' => $name];
        $player->playerPassiveService = $passives;

        return $player;
    }

    public function testMalusAlwaysAppliesToTargetEvenWhenConfiguredForActor(): void
    {
        $instruction = new MalusOutcomeInstruction();
        $instruction->setParameters(['to' => 'actor']);

        $passives = new PassiveServiceStub();
        $actor = $this->player('Actor', $passives);
        $target = $this->player('Target', $passives);

        $actor->expects($this->never())->method('put_malus');
        $target->expects($this->once())->method('put_malus');

        $instruction->execute($actor, $target, new ConditionObject());
    }

    public function testRollDifferenceIsFlooredByDivisorInMessage(): void
    {
        $instruction = new MalusOutcomeInstruction();
        $instruction->setParameters(['rollDivisor' => 3]);

        $passives = new PassiveServiceStub();

        $conditionObject = new ConditionObject();
        $conditionObject->setActorRoll(10);
        $conditionObject->setTargetRoll(4);

        $result = $instruction->execute(
            $this->player('Actor', $passives),
            $this->player('Target', $passives),
            $conditionObject
        );

        $messages = $result->getOutcomeSuccessMessages();
        $this->assertStringContainsString('+ 2 (Jet)', $messages[0]);
    }
}
