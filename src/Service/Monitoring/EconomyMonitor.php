<?php

namespace App\Service\Monitoring;

use App\Enum\MonitoringStatus;
use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * Money and goods in the world, day by day from the snapshots: a sudden
 * jump is a duplication bug or a faucet left open.
 */
final class EconomyMonitor
{
    private const SECTION = 'Économie';

    private const DAYS = 8;

    /** Day-over-day change, in percent, worth a look. */
    private const MAX_CHANGE = 25;

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** @return list<\Closure(): MonitoringResult> */
    public function checks(): array
    {
        $snapshots = new MonitoringSnapshotService($this->conn);
        $points = array_map(static fn (array $s): array => ['label' => $s['day'], 'metrics' => $s['metrics']],
            $snapshots->history(self::DAYS));
        array_unshift($points, ['label' => 'maintenant', 'metrics' => $snapshots->measure()]);

        return [
            fn (): MonitoringResult => $this->trend($points, 'Or en jeu', static fn (array $m): int => array_sum($m['gold']),
                static fn (array $m): string => 'sacs ' . $m['gold']['characters'] . ' · coffres ' . $m['gold']['structures']
                    . ' · banques ' . $m['gold']['banks'] . ' · sol ' . $m['gold']['ground']),
            fn (): MonitoringResult => $this->trend($points, 'Objets en jeu', static fn (array $m): int => $m['items'], null),
            fn (): MonitoringResult => $this->trend($points, 'XP des joueurs', static fn (array $m): int => $m['xp'], null),
        ];
    }

    /**
     * Newest first; the verdict is on the newest day-over-day change of the
     * stored snapshots, the live point is shown for context.
     *
     * @param list<array{label: string, metrics: array<string, mixed>}> $points
     * @param callable(array<string, mixed>): int $total
     * @param (callable(array<string, mixed>): string)|null $detail
     */
    private function trend(array $points, string $title, callable $total, ?callable $detail): MonitoringResult
    {
        if (count($points) < 2) {
            return new MonitoringResult(self::SECTION, $title, MonitoringStatus::Warning, BehaviourMonitor::noSnapshot());
        }

        $lines = [];
        foreach ($points as $i => $point) {
            $value = $total($point['metrics']);
            $previous = isset($points[$i + 1]) ? $total($points[$i + 1]['metrics']) : null;
            $lines[] = MonitoringResult::line($point['label'], number_format($value, 0, ',', ' ')
                . ($previous === null ? '' : ' (' . self::change($previous, $value) . ')')
                . ($detail === null ? '' : ' — ' . $detail($point['metrics'])));
        }

        $series = array_reverse(array_map(static fn (array $p): int => $total($p['metrics']), $points));
        $figure = number_format((int) end($series), 0, ',', ' ');

        if (count($points) < 3) {
            return new MonitoringResult(self::SECTION, $title, MonitoringStatus::Ok, 'Un seul relevé, pas encore de tendance.', $lines, $series, $figure);
        }

        $today = $total($points[1]['metrics']);
        $yesterday = $total($points[2]['metrics']);
        $percent = self::percent($yesterday, $today);
        $status = abs($percent) > self::MAX_CHANGE ? MonitoringStatus::Warning : MonitoringStatus::Ok;

        return new MonitoringResult(self::SECTION, $title, $status,
            self::change($yesterday, $today) . ' entre les relevés du ' . $points[2]['label'] . ' et du ' . $points[1]['label'] . '.',
            $lines, $series, $figure);
    }

    public static function percent(int $from, int $to): float
    {
        return $from === 0 ? ($to === 0 ? 0.0 : 100.0) : ($to - $from) * 100 / $from;
    }

    private static function change(int $from, int $to): string
    {
        $percent = self::percent($from, $to);

        return ($percent >= 0 ? '+' : '') . number_format($percent, 1, ',', ' ') . ' %';
    }
}
