<?php

namespace Tests\Action\OutcomeInstruction;

use App\Action\Condition\ConditionObject;
use App\Action\OutcomeInstruction\MalusOutcomeInstruction;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Action\Mock\PassiveServiceStub;

#[Group('action-outcome')]
class MalusOutcomeInstructionCharacterizationTest extends TestCase
{
    private function player(string $name, PassiveServiceStub $passives): Player&MockObject
    {
        $player = $this->createMock(Player::class);
        $player->data = (object) ['name' => $name];
        $player->playerPassiveService = $passives;

        return $player;
    }

    public function testMalusAppliesToTargetByDefault(): void
    {
        $instruction = new MalusOutcomeInstruction();
        $instruction->setParameters([]);

        $passives = new PassiveServiceStub();
        $actor = $this->player('Actor', $passives);
        $target = $this->player('Target', $passives);

        $actor->expects($this->never())->method('put_malus');
        $target->expects($this->once())->method('put_malus');

        $result = $instruction->execute($actor, $target, new ConditionObject());

        $this->assertStringContainsString('à Target.', $result->getOutcomeSuccessMessages()[0]);
    }

    public function testMalusAppliesToActorWhenConfigured(): void
    {
        $instruction = new MalusOutcomeInstruction();
        $instruction->setParameters(['to' => 'actor']);

        $passives = new PassiveServiceStub();
        $actor = $this->player('Actor', $passives);
        $target = $this->player('Target', $passives);

        $actor->expects($this->once())->method('put_malus');
        $target->expects($this->never())->method('put_malus');

        $result = $instruction->execute($actor, $target, new ConditionObject());

        $this->assertStringContainsString('à Actor.', $result->getOutcomeSuccessMessages()[0]);
    }

    public function testRollDifferenceIsFlooredByDivisor(): void
    {
        $instruction = new MalusOutcomeInstruction();

        $this->assertSame(2, $instruction->computeRollDifference(10, 4, 3));
    }

    public function testRollDifferenceNeverGoesNegative(): void
    {
        $instruction = new MalusOutcomeInstruction();

        $this->assertSame(0, $instruction->computeRollDifference(2, 10, 3));
    }

    public function testMalusTotalSumsBaseAndDifference(): void
    {
        $instruction = new MalusOutcomeInstruction();

        $this->assertSame(4, $instruction->computeMalusTotal(2, 2, false));
    }

    public function testInepuisablePassiveReducesMalusByOne(): void
    {
        $instruction = new MalusOutcomeInstruction();

        $this->assertSame(1, $instruction->computeMalusTotal(2, 0, true));
    }
}
