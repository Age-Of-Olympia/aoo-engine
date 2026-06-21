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
}
