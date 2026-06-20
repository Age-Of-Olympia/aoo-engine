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

    private function modifiers(int $bonusDamages = 0, int $bonusDefense = 0): DamageModifiers
    {
        return new DamageModifiers(
            bonusDamages: $bonusDamages,
            othersDamages: 0,
            agressivite: 0,
            faiblesse: 0,
            bonusDefense: $bonusDefense,
            othersDefense: 0,
            armure: 0,
            fragilite: 0,
        );
    }

    public function testRawDamageIsBasePlusAdditionalWithoutTheMinimumFloor(): void
    {
        // base 1-10 = -9 ; additional 0 ; rawDamage stays -9 (no min-1 here)
        $this->assertSame(-9, (new DamageCalculator())->rawDamage(1, 10, $this->modifiers()));
    }

    public function testTotalDamageIsBasePlusAdditional(): void
    {
        // base 10-4 = 6 ; additional bonusDamages 2 ; 8
        $this->assertSame(8, (new DamageCalculator())->totalDamage(10, 4, $this->modifiers(bonusDamages: 2)));
    }

    public function testTotalDamageHasAMinimumOfOne(): void
    {
        $this->assertSame(1, (new DamageCalculator())->totalDamage(1, 10, $this->modifiers()));
    }

    public function testBaseDamageIsFlooredAtZeroWhenTargetHasADefenseBonus(): void
    {
        // base -8 floored to 0 ; additional 5 - 3 = 2 ; total 2 (without the floor it would be 1)
        $this->assertSame(2, (new DamageCalculator())->totalDamage(2, 10, $this->modifiers(bonusDamages: 5, bonusDefense: 3)));
    }
}
