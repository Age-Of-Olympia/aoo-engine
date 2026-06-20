<?php

namespace Tests\Action\OutcomeInstruction;

use App\Action\OutcomeInstruction\LifeLossOutcomeInstruction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-outcome')]
class LifeLossOutcomeInstructionCharacterizationTest extends TestCase
{
    public function testDamageTakenIsThreeQuartersOfRaw(): void
    {
        $instruction = new LifeLossOutcomeInstruction();

        $this->assertSame(7, $instruction->computeDamageTaken(10));
    }

    public function testDamageTakenNeverDropsBelowOne(): void
    {
        $instruction = new LifeLossOutcomeInstruction();

        $this->assertSame(1, $instruction->computeDamageTaken(1));
    }

    public function testRecoverMalusIsHalfOfDamage(): void
    {
        $instruction = new LifeLossOutcomeInstruction();

        $this->assertSame(5, $instruction->computeRecoverMalus(10));
    }

    public function testRecoverMalusRoundsDown(): void
    {
        $instruction = new LifeLossOutcomeInstruction();

        $this->assertSame(2, $instruction->computeRecoverMalus(5));
    }

    public function testLeechIsOneThirdOfDamage(): void
    {
        $instruction = new LifeLossOutcomeInstruction();

        $this->assertSame(3, $instruction->computeLeech(10));
    }
}
