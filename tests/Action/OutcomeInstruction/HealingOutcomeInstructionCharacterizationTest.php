<?php

namespace Tests\Action\OutcomeInstruction;

use App\Action\Condition\ConditionObject;
use App\Action\OutcomeInstruction\HealingOutcomeInstruction;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('action-outcome')]
class HealingOutcomeInstructionCharacterizationTest extends TestCase
{
    private function player(string $name): Player&MockObject
    {
        $player = $this->createMock(Player::class);
        $player->data = (object) ['name' => $name];

        return $player;
    }

    public function testPvHealFloorsBaseByDivisorThenAddsBonus(): void
    {
        $instruction = new HealingOutcomeInstruction();

        $this->assertSame(8, $instruction->computePvHeal(10.0, 3.0, 2));
    }

    public function testPvHealWithoutBonus(): void
    {
        $instruction = new HealingOutcomeInstruction();

        $this->assertSame(5, $instruction->computePvHeal(10.0, 0.0, 2));
    }

    public function testPmHealSumsBaseAndBonusWithoutDivisor(): void
    {
        $instruction = new HealingOutcomeInstruction();

        $this->assertSame(10, $instruction->computePmHeal(7.0, 3.0));
    }

    public function testPvHealIsAppliedToTarget(): void
    {
        $instruction = new HealingOutcomeInstruction();
        $instruction->setParameters(['actorHealingTrait' => 10, 'divisor' => 2, 'bonusHealingTrait' => 3]);

        $target = $this->player('Target');

        $bonusCalls = [];
        $target->method('putBonus')->willReturnCallback(function ($bonus) use (&$bonusCalls) {
            $bonusCalls[] = $bonus;
            return true;
        });

        $instruction->execute($this->player('Actor'), $target, new ConditionObject());

        $this->assertContains(['pv' => 8], $bonusCalls);
    }

    public function testPvAndPmHealsBothKeepTheirMessage(): void
    {
        // PM healing used to overwrite message [0], erasing the PV heal line.
        $instruction = new HealingOutcomeInstruction();
        $instruction->setParameters(['actorHealingTrait' => 5, 'actorPMHealingTrait' => 3]);

        $target = $this->player('Target');
        $target->method('putBonus')->willReturn(true);

        $messages = $instruction->execute($this->player('Actor'), $target, new ConditionObject())
            ->getOutcomeSuccessMessages();

        $pv = array_filter($messages, static fn($m) => str_contains((string) $m, 'points de vie'));
        $pm = array_filter($messages, static fn($m) => str_contains((string) $m, 'points de mana'));
        $this->assertCount(1, $pv, 'the PV heal message must survive a simultaneous PM heal');
        $this->assertCount(1, $pm);
    }
}
