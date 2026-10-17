<?php

namespace Tests\Various;

use App\Enum\MonitoringStatus;
use App\Service\Monitoring\CronMonitor;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class CronMonitorTest extends TestCase
{
    private const NOW = '2026-10-17 12:00:00';

    /**
     * @param list<string> $texts
     */
    private function verdict(string $group, ?string $startedAt, array $texts): MonitoringStatus
    {
        $lines = array_map(static fn (string $text): array => ['label' => '', 'text' => $text], $texts);

        return CronMonitor::analyse(
            $group,
            $startedAt === null ? null : new DateTimeImmutable($startedAt),
            $lines,
            new DateTimeImmutable(self::NOW)
        )->status;
    }

    public function testVerdicts(): void
    {
        $started = 'Cron daily started (pid 1)';
        $ok = 'cron daily/10_a.php ok in 0s';
        $done = 'cron daily done 17/10/2026 03:00:05 in 5s';

        $this->assertSame(MonitoringStatus::Error, $this->verdict('daily', null, []), 'never ran');
        $this->assertSame(MonitoringStatus::Ok, $this->verdict('daily', '2026-10-17 03:00', [$started, $ok, $done]));
        $this->assertSame(MonitoringStatus::Error, $this->verdict('daily', '2026-10-16 03:00', [$started, $ok, $done]), 'skipped a night');
        $this->assertSame(MonitoringStatus::Ok, $this->verdict('weekly', '2026-09-01 03:00', [$started, $done]), 'no expected interval');
        $this->assertSame(MonitoringStatus::Warning, $this->verdict('daily', '2026-10-17 03:00', [
            $started, 'cron daily/10_a.php FAILED in 0s: Error: boom', $done,
        ]));
        $this->assertSame(MonitoringStatus::Error, $this->verdict('daily', '2026-10-17 03:00', [
            $started, 'cron daily INTERRUPTED during daily/10_a.php: memory',
        ]));
        $this->assertSame(MonitoringStatus::Running, $this->verdict('daily', '2026-10-17 11:30', [$started]));
        $this->assertSame(MonitoringStatus::Error, $this->verdict('daily', '2026-10-17 03:00', [$started]), 'died silently');
    }
}
