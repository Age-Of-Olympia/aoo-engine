<?php

namespace Tests\Action\Schema;

use App\Service\Action\Xp\AttackXpCalculator;
use App\Service\Action\Xp\FixedXpCalculator;
use App\Service\Action\Xp\StealXpCalculator;
use App\Service\Action\Xp\TrainXpCalculator;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class XpCalculatorTest extends TestCase
{
    /**
     * @param array<string, mixed> $data
     */
    private function player(array $data): Player&MockObject
    {
        $player = $this->createMock(Player::class);
        $player->data = (object) $data;

        return $player;
    }

    public function testFixedRewardMapsSuccessAndFailurePerSide(): void
    {
        $calc = new FixedXpCalculator();
        $params = ['actorSuccess' => 3, 'actorFail' => 0, 'targetSuccess' => 1, 'targetFail' => 2];
        $a = $this->player([]);
        $t = $this->player([]);

        $this->assertSame(['actor' => 3, 'target' => 1], $calc->calculate($params, true, $a, $t));
        $this->assertSame(['actor' => 0, 'target' => 2], $calc->calculate($params, false, $a, $t));
    }

    public function testAttackUsesRankDiffAndUpgradesAboveTheFloor(): void
    {
        $actor = $this->player(['rank' => 5, 'faction' => '', 'secretFaction' => '', 'isInactive' => false]);
        $actor->method('get_upgrades')->willReturn((object) ['a' => 0]);
        $target = $this->player(['rank' => 3, 'faction' => '', 'secretFaction' => '', 'isInactive' => false]);

        // base 5 - diff 2 - reduc 0 = 3, above the min of 2.
        $result = (new AttackXpCalculator())->calculate(AttackXpCalculator::defaults(), true, $actor, $target);

        $this->assertSame(3, $result['actor']);
        $this->assertSame(0, $result['target']);
    }

    public function testAttackDropsToZeroBeyondTheRankCapAndGivesTargetFailXp(): void
    {
        $actor = $this->player(['rank' => 10, 'faction' => '', 'secretFaction' => '', 'isInactive' => false]);
        $actor->method('get_upgrades')->willReturn((object) ['a' => 0]);
        $target = $this->player(['rank' => 3, 'faction' => '', 'secretFaction' => '', 'isInactive' => false]);

        // diff 7 > diffCap 3 -> 0 XP for the actor; on a miss the target gets targetFail.
        $this->assertSame(0, (new AttackXpCalculator())->calculate(AttackXpCalculator::defaults(), true, $actor, $target)['actor']);
        $this->assertSame(2, (new AttackXpCalculator())->calculate(AttackXpCalculator::defaults(), false, $actor, $target)['target']);
    }

    public function testAttackSameFactionIsReducedToOne(): void
    {
        $actor = $this->player(['rank' => 5, 'faction' => 'A', 'secretFaction' => '', 'isInactive' => false]);
        $actor->method('get_upgrades')->willReturn((object) ['a' => 0]);
        $target = $this->player(['rank' => 5, 'faction' => 'A', 'secretFaction' => '', 'isInactive' => false]);

        $this->assertSame(1, (new AttackXpCalculator())->calculate(AttackXpCalculator::defaults(), true, $actor, $target)['actor']);
    }

    public function testStealIsCappedAndGivesTargetFailOnAMiss(): void
    {
        $actor = $this->player([]);
        $actor->method('get_action_xp')->willReturn(10);
        $target = $this->player([]);

        $this->assertSame(3, (new StealXpCalculator())->calculate(StealXpCalculator::defaults(), true, $actor, $target)['actor']);
        $this->assertSame(['actor' => 0, 'target' => 2], (new StealXpCalculator())->calculate(StealXpCalculator::defaults(), false, $actor, $target));
    }

    public function testTrainAddsEnergieAndRankBonuses(): void
    {
        $actor = $this->player(['rank' => 2, 'energie' => 3]);
        $target = $this->player(['rank' => 4, 'energie' => 1]);
        $actor->expects($this->never())->method('putEnergie');
        $target->expects($this->never())->method('putEnergie');

        $result = (new TrainXpCalculator())->calculate(TrainXpCalculator::defaults(), true, $actor, $target);

        // actor: 1 + (energie>2) + (energie>0) + (rank 2<4) = 4
        $this->assertSame(4, $result['actor']);
        // target: 1 + (energie>0) only = 2
        $this->assertSame(2, $result['target']);
    }

    public function testTrainSpendsOneEnergiePerSideAsASideEffect(): void
    {
        $actor = $this->player(['rank' => 2, 'energie' => 3]);
        $target = $this->player(['rank' => 4, 'energie' => 1]);
        $actor->expects($this->once())->method('putEnergie')->with(-1);
        $target->expects($this->once())->method('putEnergie')->with(-1);

        (new TrainXpCalculator())->applySideEffects(TrainXpCalculator::defaults(), true, $actor, $target);
    }
}
