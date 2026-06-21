<?php

namespace Tests\Action\Combat;

use App\Simulation\DiceLog;
use Classes\Dice;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-combat')]
class DiceLogTest extends TestCase
{
    protected function tearDown(): void
    {
        DiceLog::stop();
    }

    public function testRollsAreNotRecordedUnlessStarted(): void
    {
        (new Dice(3))->roll(2);

        $this->assertSame([], DiceLog::stop());
    }

    public function testRecordsEachRollBetweenStartAndStop(): void
    {
        DiceLog::start();
        (new Dice(6))->roll(2);
        (new Dice(3))->roll(1);

        $rolls = DiceLog::stop();

        $this->assertCount(2, $rolls);
        $this->assertSame(6, $rolls[0]['sides']);
        $this->assertCount(2, $rolls[0]['faces']);
        $this->assertSame(3, $rolls[1]['sides']);
        $this->assertCount(1, $rolls[1]['faces']);
    }

    public function testStopClearsSoTheNextRunStartsEmpty(): void
    {
        DiceLog::start();
        (new Dice(3))->roll(1);
        DiceLog::stop();

        DiceLog::start();

        $this->assertSame([], DiceLog::stop());
    }

    public function testRecordedFacesAreWithinTheDieRange(): void
    {
        DiceLog::start();
        (new Dice(3))->roll(5);
        $rolls = DiceLog::stop();

        foreach ($rolls[0]['faces'] as $face) {
            $this->assertGreaterThanOrEqual(1, $face);
            $this->assertLessThanOrEqual(3, $face);
        }
    }
}
