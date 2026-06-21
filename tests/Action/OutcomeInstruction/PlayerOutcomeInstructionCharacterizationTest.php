<?php

namespace Tests\Action\OutcomeInstruction;

use App\Action\Condition\ConditionObject;
use App\Action\OutcomeInstruction\PlayerOutcomeInstruction;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('action-outcome')]
class PlayerOutcomeInstructionCharacterizationTest extends TestCase
{
    private function player(string $name): Player&MockObject
    {
        $player = $this->createMock(Player::class);
        $player->data = (object) ['name' => $name];

        return $player;
    }

    public function testActorNonMovementCaracUsesACaracAwareMessage(): void
    {
        $instruction = new PlayerOutcomeInstruction();
        $instruction->setParameters(['carac' => 'energie', 'value' => 4, 'player' => 'actor']);

        $actor = $this->player('Actor');
        $calls = [];
        $actor->method('putBonus')->willReturnCallback(function ($bonus) use (&$calls) {
            $calls[] = $bonus;
            return true;
        });

        $messages = $instruction->execute($actor, $this->player('Target'), new ConditionObject())
            ->getOutcomeSuccessMessages();

        $this->assertContains(['energie' => 4], $calls);
        $this->assertStringNotContainsString('mouvement', $messages[0]);
        $this->assertStringContainsString('+4', $messages[0]);
    }

    public function testActorMovementKeepsTheRunningFlavour(): void
    {
        $instruction = new PlayerOutcomeInstruction();
        $instruction->setParameters(['carac' => 'mvt', 'value' => 1, 'player' => 'actor']);

        $actor = $this->player('Actor');
        $actor->method('putBonus')->willReturn(true);

        $messages = $instruction->execute($actor, $this->player('Target'), new ConditionObject())
            ->getOutcomeSuccessMessages();

        $this->assertStringContainsString('courez', $messages[0]);
    }

    public function testTargetCaracIsAppliedToTheTarget(): void
    {
        // player:target did nothing before — the branch was empty.
        $instruction = new PlayerOutcomeInstruction();
        $instruction->setParameters(['carac' => 'mvt', 'value' => 2, 'player' => 'target']);

        $target = $this->player('Target');
        $calls = [];
        $target->method('putBonus')->willReturnCallback(function ($bonus) use (&$calls) {
            $calls[] = $bonus;
            return true;
        });

        $messages = $instruction->execute($this->player('Actor'), $target, new ConditionObject())
            ->getOutcomeSuccessMessages();

        $this->assertContains(['mvt' => 2], $calls);
        $this->assertStringContainsString('Target', $messages[0]);
    }
}
