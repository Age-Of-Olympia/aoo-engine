<?php

namespace Tests\Various;

use App\Enum\MonitoringStatus;
use App\Service\Monitoring\BehaviourMonitor;
use App\Service\Monitoring\EconomyMonitor;
use App\Service\Monitoring\MonitoringResult;
use App\Service\Monitoring\MonitoringService;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyBootstrapTrait;

class MonitoringTest extends TestCase
{
    use LegacyBootstrapTrait;

    public function testUnusualGainsStandAboveTheMedianAndTheFloor(): void
    {
        $gains = [1 => 10, 2 => 12, 3 => 15, 4 => 500, 5 => 0, 6 => -40];

        $this->assertSame([4], BehaviourMonitor::unusualGains($gains, 100));
        $this->assertSame([], BehaviourMonitor::unusualGains($gains, 1000), 'below the floor');
        $this->assertSame([], BehaviourMonitor::unusualGains([1 => 0, 2 => -5], 0), 'no positive gain');
    }

    public function testPercentChange(): void
    {
        $this->assertSame(50.0, EconomyMonitor::percent(100, 150));
        $this->assertSame(0.0, EconomyMonitor::percent(0, 0));
        $this->assertSame(100.0, EconomyMonitor::percent(0, 7));
    }

    public function testOffendersMakeTheVerdict(): void
    {
        $ok = MonitoringResult::fromOffenders('S', 'T', [], MonitoringStatus::Error, 'fine', '%d bad');
        $bad = MonitoringResult::fromOffenders('S', 'T', ['a', 'b'], MonitoringStatus::Error, 'fine', '%d bad');

        $this->assertSame([MonitoringStatus::Ok, 'fine'], [$ok->status, $ok->summary]);
        $this->assertSame([MonitoringStatus::Error, '2 bad', 2], [$bad->status, $bad->summary, count($bad->lines)]);
    }

    /** Every check runs against a real schema: a broken query fails here, not on the dashboard. */
    public function testEveryCheckRuns(): void
    {
        $this->bootstrapLegacyOrSkip('monitoring_snapshots');

        $crashed = array_filter(
            (new MonitoringService())->results(),
            static fn (MonitoringResult $r): bool => $r->section === 'Monitoring'
        );

        $this->assertSame([], array_map(static fn (MonitoringResult $r): string => $r->title . ' — ' . $r->summary, array_values($crashed)));
    }
}
