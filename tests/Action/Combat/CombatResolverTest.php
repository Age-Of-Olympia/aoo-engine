<?php

namespace Tests\Action\Combat;

use App\Action\Combat\CombatResolver;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Action\Mock\ScriptedDice;

#[Group('action-combat')]
class CombatResolverTest extends TestCase
{
    public function testSingleRollWhenNeitherAdvantageNorDisadvantage(): void
    {
        $resolver = new CombatResolver(new ScriptedDice([[7]]));

        $this->assertSame([7], $resolver->roll(2));
    }

    public function testAdvantageKeepsTheHigherOfTwoRolls(): void
    {
        $resolver = new CombatResolver(new ScriptedDice([[5], [12]]));

        $this->assertSame([12], $resolver->roll(2, advantage: true));
    }

    public function testDisadvantageKeepsTheLowerOfTwoRolls(): void
    {
        $resolver = new CombatResolver(new ScriptedDice([[12], [5]]));

        $this->assertSame([5], $resolver->roll(2, disadvantage: true));
    }

    public function testAdvantageAndDisadvantageCancelToASingleRoll(): void
    {
        $resolver = new CombatResolver(new ScriptedDice([[7], [9]]));

        $this->assertSame([7], $resolver->roll(2, advantage: true, disadvantage: true));
    }
}
