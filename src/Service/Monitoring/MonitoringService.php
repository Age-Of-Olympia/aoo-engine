<?php

namespace App\Service\Monitoring;

use App\Enum\MonitoringStatus;
use Closure;
use ReflectionFunction;
use Throwable;

/**
 * Gathers every watched source for admin/monitoring.php. A source is a class
 * whose checks() lists closures, each returning one MonitoringResult; a new
 * source is added to SOURCES.
 */
final class MonitoringService
{
    private const SOURCES = [
        CronMonitor::class, IntegrityMonitor::class, StuckPlayersMonitor::class,
        BehaviourMonitor::class, EconomyMonitor::class, PlatformMonitor::class,
    ];

    /** @return list<MonitoringResult> */
    public function results(): array
    {
        $results = [];
        foreach (self::SOURCES as $source) {
            // A broken check is a red card of its own, never a blank dashboard.
            try {
                $checks = (new $source())->checks();
            } catch (Throwable $e) {
                $results[] = self::failure($source, $e);
                continue;
            }
            foreach ($checks as $check) {
                try {
                    $results[] = $check();
                } catch (Throwable $e) {
                    $results[] = self::failure(substr(strrchr($source, '\\'), 1) . '::' . self::name($check), $e);
                }
            }
        }

        return $results;
    }

    private static function failure(string $what, Throwable $e): MonitoringResult
    {
        return new MonitoringResult('Monitoring', $what, MonitoringStatus::Error,
            'Le contrôle a planté — ' . get_class($e) . ' : ' . $e->getMessage());
    }

    private static function name(Closure $check): string
    {
        return (new ReflectionFunction($check))->getName();
    }
}
