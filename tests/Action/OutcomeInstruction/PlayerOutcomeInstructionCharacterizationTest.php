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
        $instruction->setParameters(['carac' => 'energie', 'value' => 4]);
        self::onSelf($instruction);

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
        $instruction->setParameters(['carac' => 'mvt', 'value' => 1]);
        self::onSelf($instruction);

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
        $instruction->setParameters(['carac' => 'mvt', 'value' => 2]);

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

    public function testVisibleEffectDoesNotTouchPlayerServiceDuringSimulation(): void
    {
        // SimulatedPlayer has no playerService; the visible branch used to NPE.
        $instruction = new PlayerOutcomeInstruction();
        $instruction->setParameters(['carac' => 'visible', 'value' => 1]);
        self::onSelf($instruction);

        $actor = $this->player('Actor');
        $actor->method('isSimulated')->willReturn(true);

        $messages = $instruction->execute($actor, $this->player('Target'), new ConditionObject())
            ->getOutcomeSuccessMessages();

        $this->assertStringContainsString('furtivité', $messages[0]);
    }

    public function testDiscretionWritesInvisible(): void
    {
        $this->assertSame(['invisible'], $this->visibleWrites(['carac' => 'visible', 'appearance' => 'invisible']));
    }

    public function testACamouflageWritesTheBorrowedRace(): void
    {
        // Not cast to an int: 'nain' used to become 0, a race the map ignores.
        $this->assertSame(['nain'], $this->visibleWrites(['carac' => 'visible', 'appearance' => 'nain', 'value' => 0]));
    }

    public function testNoAppearanceWritesNothing(): void
    {
        $this->assertSame([], $this->visibleWrites(['carac' => 'visible', 'value' => 0]));
    }

    /**
     * @param array<string, mixed> $parameters
     * @return list<mixed> values passed to playerUpdateVisible
     */
    private function visibleWrites(array $parameters): array
    {
        $instruction = new PlayerOutcomeInstruction();
        $instruction->setParameters($parameters);
        self::onSelf($instruction);

        $writes = [];
        $service = $this->createMock(\App\Service\PlayerService::class);
        $service->method('playerUpdateVisible')->willReturnCallback(function ($value) use (&$writes) {
            $writes[] = $value;
        });

        $actor = $this->player('Actor');
        $actor->method('isSimulated')->willReturn(false);
        $actor->playerService = $service;

        $instruction->execute($actor, $this->player('Target'), new ConditionObject());

        return $writes;
    }

    /** The outcome's toggle is what routes the instruction now. */
    private static function onSelf(\App\Entity\OutcomeInstruction $instruction): void
    {
        $instruction->setOutcome((new \App\Entity\ActionOutcome())->setApplyTo(\App\Enum\OutcomeTarget::Self));
    }
}
