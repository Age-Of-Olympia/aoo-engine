<?php

namespace Tests\Action\OutcomeInstruction;

use App\Action\Condition\ConditionObject;
use App\Action\OutcomeInstruction\ApplyStatusOutcomeInstruction;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-outcome')]
class ApplyStatusOutcomeInstructionCharacterizationTest extends TestCase
{
    public function testAppliesTheEffectToTheActorWithAMessage(): void
    {
        $instruction = new ApplyStatusOutcomeInstruction();
        $instruction->setParameters([
            'adrenaline' => true,
            'duration' => 0,
            'value' => 2,
            'stackable' => false,
        ]);
        self::onSelf($instruction);

        $actor = $this->createMock(Player::class);
        $actor->data = (object) ['name' => 'Actor'];
        $actor->expects($this->once())->method('add_effect')->with('adrenaline', 0, 2, false);

        $target = $this->createMock(Player::class);
        $target->data = (object) ['name' => 'Target'];

        $result = $instruction->execute($actor, $target, new ConditionObject());

        $this->assertTrue($result->isSuccess());
        $message = $result->getOutcomeSuccessMessages()[0];
        $this->assertStringContainsString('adrenaline', $message);
        $this->assertStringContainsString('Actor', $message);
    }

    public function testReadsTheEffectFromTheNewEffectField(): void
    {
        // New shape: effect/apply are normal fields (was the first param key).
        $instruction = new ApplyStatusOutcomeInstruction();
        $instruction->setParameters([
            'effect' => 'adrenaline', 'apply' => true,
            'duration' => 0, 'value' => 2, 'stackable' => false,
        ]);
        self::onSelf($instruction);

        $actor = $this->createMock(Player::class);
        $actor->data = (object) ['name' => 'Actor'];
        $actor->expects($this->once())->method('add_effect')->with('adrenaline', 0, 2, false);
        $target = $this->createMock(Player::class);
        $target->data = (object) ['name' => 'Target'];

        $instruction->execute($actor, $target, new ConditionObject());
    }

    public function testApplyFalseEndsTheEffectInsteadOfAddingIt(): void
    {
        $instruction = new ApplyStatusOutcomeInstruction();
        $instruction->setParameters(['effect' => 'protection', 'apply' => false, 'duration' => 1]);
        self::onSelf($instruction);

        $actor = $this->createMock(Player::class);
        $actor->data = (object) ['name' => 'Actor'];
        $actor->expects($this->once())->method('end_effect')->with('protection');
        $actor->expects($this->never())->method('add_effect');
        $target = $this->createMock(Player::class);
        $target->data = (object) ['name' => 'Target'];

        $instruction->execute($actor, $target, new ConditionObject());
    }

    public function testEscapesTheEffectNameBeforeItReachesTheOutcomeHtml(): void
    {
        $payload = '<img src=x onerror=alert(1)>';
        $instruction = new ApplyStatusOutcomeInstruction();
        $instruction->setParameters([
            $payload => true,
            'duration' => 0,
            'value' => 2,
        ]);
        self::onSelf($instruction);

        $actor = $this->createMock(Player::class);
        $actor->data = (object) ['name' => 'Actor'];
        $actor->method('add_effect');

        $target = $this->createMock(Player::class);
        $target->data = (object) ['name' => 'Target'];

        // An unknown effect name gets the catalog's fallback icon (orthogonal to
        // the escaping); the point is that the name itself must not reach the
        // HTML unescaped.
        $message = $instruction->execute($actor, $target, new ConditionObject())->getOutcomeSuccessMessages()[0];

        $this->assertStringNotContainsString('<img', $message);
        $this->assertStringContainsString('&lt;img', $message);
    }

    /** The outcome's toggle is what routes the instruction now. */
    private static function onSelf(\App\Entity\OutcomeInstruction $instruction): void
    {
        $instruction->setOutcome((new \App\Entity\ActionOutcome())->setApplyTo(\App\Enum\OutcomeTarget::Self));
    }
}
