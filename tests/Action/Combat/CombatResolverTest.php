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

    public function testResolveHitsWhenActorBeatsTarget(): void
    {
        $result = (new CombatResolver())->resolve(12, 9);

        $this->assertTrue($result->hit);
        $this->assertSame(12, $result->actorTotal);
        $this->assertSame(9, $result->targetTotal);
    }

    public function testResolveHitsOnTie(): void
    {
        $this->assertTrue((new CombatResolver())->resolve(7, 7)->hit);
    }

    public function testResolveMissesWhenActorLoses(): void
    {
        $this->assertFalse((new CombatResolver())->resolve(5, 10)->hit);
    }

    public function testResolveMissesWhenTargetIsOutOfReach(): void
    {
        $result = (new CombatResolver())->resolve(12, 9, reachedTarget: false);

        $this->assertFalse($result->hit);
        $this->assertFalse($result->reachedTarget);
    }
}
