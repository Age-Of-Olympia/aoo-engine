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
    protected function setUp(): void
    {
        if (!defined('EFFECTS_HIDDEN')) {
            define('EFFECTS_HIDDEN', []);
        }
        if (!defined('EFFECTS_RA_FONT')) {
            // Mirror the canonical test set (shared global constant) so this test
            // doesn't poison OptionCatalog/effects-derived tests, whatever the order.
            define('EFFECTS_RA_FONT', ['maladresse' => 'ra-x', 'protection' => 'ra-y', 'adrenaline' => 'ra-z']);
        }
    }

    public function testAppliesTheEffectToTheActorWithAMessage(): void
    {
        $instruction = new ApplyStatusOutcomeInstruction();
        $instruction->setParameters([
            'adrenaline' => true,
            'player' => 'actor',
            'duration' => 1,
            'value' => 2,
            'stackable' => false,
        ]);

        $actor = $this->createMock(Player::class);
        $actor->data = (object) ['name' => 'Actor'];
        $actor->expects($this->once())->method('add_effect')->with('adrenaline', 1, 2, false);

        $target = $this->createMock(Player::class);
        $target->data = (object) ['name' => 'Target'];

        $result = $instruction->execute($actor, $target, new ConditionObject());

        $this->assertTrue($result->isSuccess());
        $message = $result->getOutcomeSuccessMessages()[0];
        $this->assertStringContainsString('adrenaline', $message);
        $this->assertStringContainsString('Actor', $message);
    }

    public function testEscapesTheEffectNameBeforeItReachesTheOutcomeHtml(): void
    {
        $payload = '<img src=x onerror=alert(1)>';
        $instruction = new ApplyStatusOutcomeInstruction();
        $instruction->setParameters([
            $payload => true,
            'player' => 'actor',
            'duration' => 1,
            'value' => 2,
        ]);

        $actor = $this->createMock(Player::class);
        $actor->data = (object) ['name' => 'Actor'];
        $actor->method('add_effect');

        $target = $this->createMock(Player::class);
        $target->data = (object) ['name' => 'Target'];

        // An unknown effect name is not a key of EFFECTS_RA_FONT, which raises an
        // expected "undefined array key" warning (orthogonal to the escaping); the
        // point is that the name itself must not reach the HTML unescaped.
        $message = @$instruction->execute($actor, $target, new ConditionObject())->getOutcomeSuccessMessages()[0];

        $this->assertStringNotContainsString('<img', $message);
        $this->assertStringContainsString('&lt;img', $message);
    }
}
