<?php

namespace App\Service\Monitoring;

use App\Enum\MonitoringStatus;

/**
 * One card of the admin monitoring dashboard: what is watched, how it is
 * doing, and the raw lines behind the verdict.
 */
final class MonitoringResult
{
    /** Lines shown under a card before the rest is summed up. */
    private const MAX_LINES = 100;

    /**
     * @param list<array{label: string, text: string, status: ?MonitoringStatus}> $lines
     * @param list<int> $series values over time, oldest first, drawn as a sparkline
     * @param string|null $figure the current value, shown large
     */
    public function __construct(
        public readonly string $section,
        public readonly string $title,
        public readonly MonitoringStatus $status,
        public readonly string $summary,
        public readonly array $lines = [],
        public readonly array $series = [],
        public readonly ?string $figure = null,
    ) {
    }

    /**
     * The common shape of a check: a list of offenders, OK when empty.
     *
     * @param list<string> $offenders one line each
     * @param string $badSummary %d is replaced by the offender count
     */
    public static function fromOffenders(
        string $section,
        string $title,
        array $offenders,
        MonitoringStatus $whenAny,
        string $okSummary,
        string $badSummary,
    ): self {
        if ($offenders === []) {
            return new self($section, $title, MonitoringStatus::Ok, $okSummary);
        }

        $lines = array_map(static fn (string $text): array => ['label' => '', 'text' => $text, 'status' => null],
            array_slice($offenders, 0, self::MAX_LINES));
        if (count($offenders) > self::MAX_LINES) {
            $lines[] = ['label' => '', 'text' => '… et ' . (count($offenders) - self::MAX_LINES) . ' autres', 'status' => null];
        }

        return new self($section, $title, $whenAny, sprintf($badSummary, count($offenders)), $lines);
    }

    /** @return array{label: string, text: string, status: ?MonitoringStatus} */
    public static function line(string $label, string $text, ?MonitoringStatus $status = null): array
    {
        return ['label' => $label, 'text' => $text, 'status' => $status];
    }
}
