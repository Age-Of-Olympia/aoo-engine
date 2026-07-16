<?php

namespace Tests\Various;

use App\Service\TurnScheduleService;
use PHPUnit\Framework\TestCase;

/**
 * Pure-function tests for TurnScheduleService: turn duration derived from
 * the speed carac, and the manual reschedule window that replaces the old
 * "DLA glissante" option.
 */
class TurnScheduleServiceTest extends TestCase
{
    public function testTurnDurationAtBaselineSpeedIsOneDay(): void
    {
        $this->assertSame(86400, TurnScheduleService::turnDurationSeconds(10));
    }

    public function testEachSpeedPointAboveBaselineShortensTurnByOneHour(): void
    {
        $this->assertSame(82800, TurnScheduleService::turnDurationSeconds(11));
        $this->assertSame(68400, TurnScheduleService::turnDurationSeconds(15));
    }

    public function testSpeedBelowBaselineLengthensTurn(): void
    {
        $this->assertSame(90000, TurnScheduleService::turnDurationSeconds(9));
    }

    public function testWindowSpansFromCurrentTurnToPotentialNextOne(): void
    {
        // 1 752 600 000 is minute-aligned: bounds need no rounding
        $nextTurnTime = 1752600000;

        $window = TurnScheduleService::rescheduleWindow($nextTurnTime, 10);

        $this->assertSame($nextTurnTime, $window['min']);
        $this->assertSame($nextTurnTime + 86400, $window['max']);
    }

    public function testWindowBoundsAreAlignedOnWholeMinutes(): void
    {
        // 30 s past a whole minute: min rounds up, max rounds down, so both
        // are reachable through a minute-granularity datetime-local input
        $nextTurnTime = 1752600030;

        $window = TurnScheduleService::rescheduleWindow($nextTurnTime, 10);

        $this->assertSame(1752600060, $window['min']);
        $this->assertSame(1752600030 + 86400 - 30, $window['max']);
    }

    public function testCandidateInsideWindowIsAccepted(): void
    {
        $nextTurnTime = 1752600000;

        $this->assertTrue(TurnScheduleService::isWithinRescheduleWindow($nextTurnTime, $nextTurnTime, 10));
        $this->assertTrue(TurnScheduleService::isWithinRescheduleWindow($nextTurnTime + 3600, $nextTurnTime, 10));
        $this->assertTrue(TurnScheduleService::isWithinRescheduleWindow($nextTurnTime + 86400, $nextTurnTime, 10));
    }

    public function testCandidateBeforeCurrentTurnIsRejected(): void
    {
        // moving the turn earlier would grant a free turn
        $nextTurnTime = 1752600000;

        $this->assertFalse(TurnScheduleService::isWithinRescheduleWindow($nextTurnTime - 60, $nextTurnTime, 10));
    }

    public function testCandidateAfterPotentialNextTurnIsRejected(): void
    {
        $nextTurnTime = 1752600000;

        $this->assertFalse(TurnScheduleService::isWithinRescheduleWindow($nextTurnTime + 86400 + 60, $nextTurnTime, 10));
    }

    public function testWindowMaxFollowsSpeedCarac(): void
    {
        $nextTurnTime = 1752600000;

        $window = TurnScheduleService::rescheduleWindow($nextTurnTime, 12);

        $this->assertSame($nextTurnTime + 79200, $window['max']);
    }
}
