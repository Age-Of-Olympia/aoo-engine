<?php

namespace Tests\Action\Combat;

use App\Action\Combat\DamageCalculator;
use App\Action\Combat\DamageModifiers;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-combat')]
class DamageCalculatorTest extends TestCase
{
    public function testAdditionalDamagesIsOffenseMinusDefense(): void
    {
        $modifiers = new DamageModifiers(
            bonusDamages: 5,
            othersDamages: 2,
            agressivite: 1,
            faiblesse: 0,
            bonusDefense: 3,
            othersDefense: 1,
            armure: 2,
            fragilite: 0,
        );

        // offense 5+2+1-0 = 8 ; defense 3+1+2-0 = 6 ; 8 - 6 = 2
        $this->assertSame(2, (new DamageCalculator())->additionalDamages($modifiers));
    }

    public function testFaiblesseReducesOffenseAndFragiliteReducesDefense(): void
    {
        $modifiers = new DamageModifiers(
            bonusDamages: 0,
            othersDamages: 0,
            agressivite: 0,
            faiblesse: 3,
            bonusDefense: 0,
            othersDefense: 0,
            armure: 0,
            fragilite: 5,
        );

        // offense 0-3 = -3 ; defense 0-5 = -5 ; -3 - (-5) = 2
        $this->assertSame(2, (new DamageCalculator())->additionalDamages($modifiers));
    }
}
